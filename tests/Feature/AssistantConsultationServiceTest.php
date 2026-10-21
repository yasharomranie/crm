<?php

namespace Tests\Feature;

use App\Domain\Ai\AssistantConsultationService;
use App\Models\AiAssistantConsultation;
use App\Models\AiAssistantProfile;
use App\Models\AiProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AssistantConsultationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_available_consultants_excludes_the_requester_itself(): void
    {
        $user = User::factory()->create();
        $requester = AiAssistantProfile::factory()->create(['created_by' => $user->id]);

        $available = app(AssistantConsultationService::class)->availableConsultantsFor($requester, $user);

        $this->assertFalse($available->contains('id', $requester->id));
    }

    public function test_available_consultants_excludes_a_profile_marked_not_consultable(): void
    {
        $user = User::factory()->create();
        $requester = AiAssistantProfile::factory()->create(['created_by' => $user->id]);
        $notConsultable = AiAssistantProfile::factory()->create(['created_by' => $user->id, 'consultable' => false]);

        $available = app(AssistantConsultationService::class)->availableConsultantsFor($requester, $user);

        $this->assertFalse($available->contains('id', $notConsultable->id));
    }

    public function test_available_consultants_excludes_a_private_profile_owned_by_someone_else(): void
    {
        $user = User::factory()->create();
        $requester = AiAssistantProfile::factory()->create(['created_by' => $user->id]);

        $other = User::factory()->create();
        $othersPrivate = AiAssistantProfile::factory()->create(['created_by' => $other->id, 'is_shared' => false]);

        $available = app(AssistantConsultationService::class)->availableConsultantsFor($requester, $user);

        $this->assertFalse($available->contains('id', $othersPrivate->id));
    }

    public function test_available_consultants_includes_a_shared_profile_owned_by_someone_else(): void
    {
        $user = User::factory()->create();
        $requester = AiAssistantProfile::factory()->create(['created_by' => $user->id]);

        $other = User::factory()->create();
        $othersShared = AiAssistantProfile::factory()->create(['created_by' => $other->id, 'is_shared' => true]);

        $available = app(AssistantConsultationService::class)->availableConsultantsFor($requester, $user);

        $this->assertTrue($available->contains('id', $othersShared->id));
    }

    public function test_consult_calls_the_target_with_no_tools_and_records_the_exchange(): void
    {
        AiProvider::factory()->active()->create();
        $requester = AiAssistantProfile::factory()->create(['description' => 'دستیار فروش']);
        $target = AiAssistantProfile::factory()->create(['description' => 'دستیار حقوقی']);

        Http::fake([
            '*/chat/completions' => Http::response([
                'choices' => [['message' => ['role' => 'assistant', 'content' => 'پاسخ تخصصی حقوقی.']]],
            ]),
        ]);

        $result = app(AssistantConsultationService::class)->consult($requester, $target, 'آیا این قرارداد معتبر است؟');

        $this->assertSame('پاسخ تخصصی حقوقی.', $result['answer']);
        $this->assertSame($target->displayName(), $result['consulted']);

        Http::assertSent(function ($request) {
            $data = $request->data();

            return ! array_key_exists('tools', $data);
        });

        $consultation = AiAssistantConsultation::sole();
        $this->assertSame($requester->id, $consultation->consulting_ai_assistant_profile_id);
        $this->assertSame($target->id, $consultation->consulted_ai_assistant_profile_id);
        $this->assertSame('آیا این قرارداد معتبر است؟', $consultation->question);
        $this->assertSame('پاسخ تخصصی حقوقی.', $consultation->answer);
    }

    public function test_consult_returns_an_error_and_records_nothing_when_no_provider_is_active(): void
    {
        $requester = AiAssistantProfile::factory()->create();
        $target = AiAssistantProfile::factory()->create();

        $result = app(AssistantConsultationService::class)->consult($requester, $target, 'سؤال؟');

        $this->assertArrayHasKey('error', $result);
        $this->assertSame(0, AiAssistantConsultation::count());
    }

    public function test_consult_returns_an_error_and_records_nothing_when_the_provider_request_fails(): void
    {
        AiProvider::factory()->active()->create();
        $requester = AiAssistantProfile::factory()->create();
        $target = AiAssistantProfile::factory()->create();

        Http::fake(['*/chat/completions' => Http::response(['error' => ['message' => 'quota exceeded']], 429)]);

        $result = app(AssistantConsultationService::class)->consult($requester, $target, 'سؤال؟');

        $this->assertArrayHasKey('error', $result);
        $this->assertSame(0, AiAssistantConsultation::count());
    }

    private function fakeAnswers(string ...$contents): void
    {
        $sequence = Http::sequence();

        foreach ($contents as $content) {
            $sequence->push(['choices' => [['message' => ['role' => 'assistant', 'content' => $content]]]]);
        }

        Http::fake(['*/chat/completions' => $sequence]);
    }

    private function asAdmin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));
        $this->actingAs($admin);

        return $admin;
    }

    public function test_the_consulted_assistant_answers_with_its_own_knowledge_notes(): void
    {
        AiProvider::factory()->active()->create();
        $requester = AiAssistantProfile::factory()->create();
        $target = AiAssistantProfile::factory()->create(['description' => 'دستیار حقوقی', 'knowledge_auto_save' => true]);
        $target->recordAiSuggestedKnowledge('حداکثر مهلت فسخ قرارداد هفت روز است.');
        $this->fakeAnswers('هفت روز.');

        app(AssistantConsultationService::class)->consult($requester, $target, 'مهلت فسخ چقدر است؟');

        Http::assertSent(function ($request) {
            $system = $request->data()['messages'][0]['content'];

            return str_contains($system, 'دستیار حقوقی') && str_contains($system, 'حداکثر مهلت فسخ قرارداد هفت روز است.');
        });
    }

    public function test_the_consulted_assistant_can_use_the_lookups_its_owner_granted_it(): void
    {
        $this->asAdmin();
        AiProvider::factory()->active()->create();
        $requester = AiAssistantProfile::factory()->create();
        $target = AiAssistantProfile::factory()->create(['allowed_tools' => ['check_support_ticket_status']]);

        Http::fake([
            '*/chat/completions' => Http::sequence()
                ->push(['choices' => [['message' => ['role' => 'assistant', 'content' => null, 'tool_calls' => [[
                    'id' => 'call_1',
                    'type' => 'function',
                    'function' => ['name' => 'check_support_ticket_status', 'arguments' => '{}'],
                ]]]]]])
                ->push(['choices' => [['message' => ['role' => 'assistant', 'content' => 'تیکت بازی نیست.']]]]),
        ]);

        $result = app(AssistantConsultationService::class)->consult($requester, $target, 'تیکت باز داریم؟');

        $this->assertSame('تیکت بازی نیست.', $result['answer']);
        $this->assertSame(['check_support_ticket_status'], $result['tools_used']);

        $requests = Http::recorded();
        $this->assertCount(2, $requests);
        $this->assertSame(['check_support_ticket_status'], collect($requests[0][0]->data()['tools'])->pluck('function.name')->all());
        $this->assertContains('tool', collect($requests[1][0]->data()['messages'])->pluck('role')->all());
    }

    public function test_the_consulted_assistant_is_never_offered_consulting_or_knowledge_tools(): void
    {
        $this->asAdmin();
        AiProvider::factory()->active()->create();
        $requester = AiAssistantProfile::factory()->create();
        $target = AiAssistantProfile::factory()->create(['allowed_tools' => ['check_support_ticket_status'], 'fetch_url_enabled' => true]);
        $this->fakeAnswers('پاسخ.');

        app(AssistantConsultationService::class)->consult($requester, $target, 'سؤال؟');

        Http::assertSent(function ($request) {
            $names = collect($request->data()['tools'] ?? [])->pluck('function.name')->all();

            return $names === ['check_support_ticket_status'];
        });
    }

    public function test_a_user_without_the_report_permission_gives_the_consulted_assistant_no_tools(): void
    {
        $this->actingAs(User::factory()->create());
        AiProvider::factory()->active()->create();
        $requester = AiAssistantProfile::factory()->create();
        $target = AiAssistantProfile::factory()->create(['allowed_tools' => ['check_support_ticket_status']]);
        $this->fakeAnswers('پاسخ.');

        app(AssistantConsultationService::class)->consult($requester, $target, 'سؤال؟');

        Http::assertSent(fn ($request) => ! array_key_exists('tools', $request->data()));
    }
}
