<?php

namespace Tests\Feature;

use App\Enums\AiMessageRole;
use App\Filament\Pages\AiAssistant;
use App\Models\AiAssistantConsultation;
use App\Models\AiAssistantProfile;
use App\Models\AiConversation;
use App\Models\AiProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The built-in management assistant can ask the user's own consultable
 * specialist assistants (consult_custom_assistant): read-only, answered from
 * the specialist's knowledge and granted lookups.
 */
class AiAssistantManagerConsultTest extends TestCase
{
    use RefreshDatabase;

    private function manager(bool $canReport = true): User
    {
        $this->artisan('access-control:sync');

        $role = Role::create(['name' => 'مدیر مشورت '.fake()->unique()->word()]);
        $role->givePermissionTo(Permission::whereIn('name', $canReport ? ['ai-assistant.access', 'ai-assistant.report'] : ['ai-assistant.access'])->get());

        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);

        return $user;
    }

    private function reply(?string $content, array $toolCalls = []): array
    {
        return ['choices' => [['message' => ['role' => 'assistant', 'content' => $content] + ($toolCalls ? ['tool_calls' => $toolCalls] : [])]]];
    }

    private function consultCall(int $assistantId, string $question): array
    {
        return [[
            'id' => 'call_1',
            'type' => 'function',
            'function' => ['name' => 'consult_custom_assistant', 'arguments' => json_encode(['assistant_id' => $assistantId, 'question' => $question])],
        ]];
    }

    private function toolNamesOfRequest(int $index): array
    {
        return collect(json_decode(Http::recorded()[$index][0]->body(), true)['tools'] ?? [])->pluck('function.name')->all();
    }

    public function test_the_manager_assistant_consults_a_specialist_and_gets_its_answer(): void
    {
        AiProvider::factory()->active()->create();
        $user = $this->manager();
        $specialist = AiAssistantProfile::factory()->create(['created_by' => $user->id, 'consultable' => true, 'description' => 'دستیار حقوقی']);

        Http::fake(['*/chat/completions' => Http::sequence()
            ->push($this->reply(null, $this->consultCall($specialist->id, 'مهلت فسخ چقدر است؟')))
            ->push($this->reply('مهلت فسخ هفت روز است.'))
            ->push($this->reply('به گفتهٔ دستیار حقوقی: هفت روز.')),
        ]);

        Livewire::test(AiAssistant::class)
            ->set('body', 'مهلت فسخ قرارداد چقدر است؟')
            ->call('send');

        $this->assertContains('consult_custom_assistant', $this->toolNamesOfRequest(0));

        $toolMessage = AiConversation::sole()->messages->firstWhere('role', AiMessageRole::Tool);
        $payload = json_decode($toolMessage->content, true);

        $this->assertSame('مهلت فسخ هفت روز است.', $payload['answer']);
        $this->assertSame($specialist->id, $payload['consulted_profile_id']);
        $this->assertDatabaseCount((new AiAssistantConsultation)->getTable(), 0);
    }

    public function test_the_specialist_is_not_offered_the_consult_tool_back(): void
    {
        AiProvider::factory()->active()->create();
        $user = $this->manager();
        $specialist = AiAssistantProfile::factory()->create(['created_by' => $user->id, 'consultable' => true]);

        Http::fake(['*/chat/completions' => Http::sequence()
            ->push($this->reply(null, $this->consultCall($specialist->id, 'سؤال؟')))
            ->push($this->reply('پاسخ.'))
            ->push($this->reply('تمام.')),
        ]);

        Livewire::test(AiAssistant::class)->set('body', 'بپرس')->call('send');

        $this->assertNotContains('consult_custom_assistant', $this->toolNamesOfRequest(1));
    }

    public function test_the_tool_is_not_offered_without_a_consultable_specialist(): void
    {
        AiProvider::factory()->active()->create();
        $user = $this->manager();
        AiAssistantProfile::factory()->create(['created_by' => $user->id, 'consultable' => false]);
        Http::fake(['*/chat/completions' => Http::sequence()->push($this->reply('سلام.'))]);

        Livewire::test(AiAssistant::class)->set('body', 'سلام')->call('send');

        $this->assertNotContains('consult_custom_assistant', $this->toolNamesOfRequest(0));
    }

    public function test_someone_elses_private_specialist_cannot_be_consulted(): void
    {
        AiProvider::factory()->active()->create();
        $this->manager();
        $other = AiAssistantProfile::factory()->create(['created_by' => User::factory()->create()->id, 'consultable' => true, 'is_shared' => false]);

        Http::fake(['*/chat/completions' => Http::sequence()
            ->push($this->reply(null, $this->consultCall($other->id, 'سؤال؟')))
            ->push($this->reply('ممکن نبود.')),
        ]);

        Livewire::test(AiAssistant::class)->set('body', 'بپرس')->call('send');

        $payload = json_decode(AiConversation::sole()->messages->firstWhere('role', AiMessageRole::Tool)->content, true);

        $this->assertArrayHasKey('error', $payload);
        $this->assertCount(2, Http::recorded());
    }

    public function test_the_tool_is_not_offered_without_the_report_permission(): void
    {
        AiProvider::factory()->active()->create();
        $user = $this->manager(canReport: false);
        AiAssistantProfile::factory()->create(['created_by' => $user->id, 'consultable' => true]);
        Http::fake(['*/chat/completions' => Http::sequence()->push($this->reply('اجازه ندارید.'))]);

        Livewire::test(AiAssistant::class)->set('body', 'سلام')->call('send');

        $this->assertNotContains('consult_custom_assistant', $this->toolNamesOfRequest(0));
    }
}
