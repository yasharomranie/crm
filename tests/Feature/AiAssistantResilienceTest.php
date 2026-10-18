<?php

namespace Tests\Feature;

use App\Enums\AiMessageRole;
use App\Exceptions\AiProviderException;
use App\Filament\Pages\AiAssistant;
use App\Models\AiConversation;
use App\Models\AiProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The assistant should survive a flaky provider, an endless tool loop and a
 * very long conversation without failing or sending the whole history.
 */
class AiAssistantResilienceTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAssistantUser(): User
    {
        $this->artisan('access-control:sync');

        $role = Role::create(['name' => 'گزارش‌گیر '.uniqid()]);
        $role->givePermissionTo(Permission::whereIn('name', ['ai-assistant.access', 'ai-assistant.report', 'tasks.view_any'])->get());

        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);

        return $user;
    }

    private function answer(string $content): array
    {
        return ['choices' => [['message' => ['role' => 'assistant', 'content' => $content]]]];
    }

    private function toolCall(string $id): array
    {
        return ['choices' => [['message' => [
            'role' => 'assistant',
            'content' => null,
            'tool_calls' => [['id' => $id, 'type' => 'function', 'function' => ['name' => 'get_task_summary', 'arguments' => '{}']]],
        ]]]];
    }

    public function test_a_rate_limited_request_is_retried(): void
    {
        Sleep::fake();
        Http::fakeSequence()->push(['error' => ['message' => 'slow down']], 429)->push($this->answer('سلام'));

        $message = AiProvider::factory()->active()->create()->chatCompletion([['role' => 'user', 'content' => 'hi']]);

        $this->assertSame('سلام', $message['content']);
        Http::assertSentCount(2);
    }

    public function test_a_rejected_request_is_not_retried(): void
    {
        Sleep::fake();
        Http::fake(['*' => Http::response(['error' => ['message' => 'Invalid API key']], 401)]);

        try {
            AiProvider::factory()->active()->create()->chatCompletion([['role' => 'user', 'content' => 'hi']]);
            $this->fail('Expected an AiProviderException.');
        } catch (AiProviderException $exception) {
            $this->assertStringContainsString('Invalid API key', $exception->getMessage());
        }

        Http::assertSentCount(1);
    }

    public function test_a_dropped_connection_becomes_a_friendly_error(): void
    {
        Sleep::fake();
        Http::fake(fn () => throw new ConnectionException('cURL error 28: timed out'));

        $this->expectException(AiProviderException::class);
        $this->expectExceptionMessage('اتصال به سرویس هوش مصنوعی');

        AiProvider::factory()->active()->create()->chatCompletion([['role' => 'user', 'content' => 'hi']]);
    }

    public function test_when_the_tool_rounds_run_out_the_assistant_still_answers(): void
    {
        AiProvider::factory()->active()->create();
        Http::fakeSequence()
            ->push($this->toolCall('c1'))
            ->push($this->toolCall('c2'))
            ->push($this->toolCall('c3'))
            ->push($this->toolCall('c4'))
            ->push($this->toolCall('c5'))
            ->push($this->answer('با اطلاعات فعلی: وظیفه‌ای عقب نیست؛ بقیه کامل نشد.'));
        $this->actingAsAssistantUser();

        Livewire::test(AiAssistant::class)->set('body', 'همه چیز را بررسی کن')->call('send');

        $last = AiConversation::sole()->messages->last();
        $this->assertSame(AiMessageRole::Assistant, $last->role);
        $this->assertStringContainsString('بقیه کامل نشد', $last->content);

        Http::assertSent(fn (Request $request) => ! array_key_exists('tools', $request->data())
            && str_contains(json_encode($request->data()['messages'], JSON_UNESCAPED_UNICODE), 'محدودیت تعداد مراحل'));
    }

    public function test_only_the_recent_questions_are_sent_and_old_tool_output_is_shortened(): void
    {
        AiProvider::factory()->active()->create();
        Http::fake(['*' => Http::response($this->answer('باشه'))]);
        $user = $this->actingAsAssistantUser();

        $conversation = AiConversation::create(['user_id' => $user->id, 'title' => 'طولانی']);

        foreach (range(1, 15) as $turn) {
            $conversation->messages()->create(['role' => AiMessageRole::User, 'content' => "سؤال {$turn}"]);

            if ($turn === 10) {
                $conversation->messages()->create([
                    'role' => AiMessageRole::Assistant,
                    'content' => null,
                    'tool_calls' => [['id' => 'old', 'type' => 'function', 'function' => ['name' => 'get_task_summary', 'arguments' => '{}']]],
                ]);
                $conversation->messages()->create([
                    'role' => AiMessageRole::Tool,
                    'content' => str_repeat('x', 6000),
                    'tool_call_id' => 'old',
                    'tool_name' => 'get_task_summary',
                ]);
            }

            $conversation->messages()->create(['role' => AiMessageRole::Assistant, 'content' => "پاسخ {$turn}"]);
        }

        Livewire::test(AiAssistant::class)
            ->set('conversationId', $conversation->id)
            ->set('body', 'سؤال جدید')
            ->call('send');

        Http::assertSent(function (Request $request) {
            $messages = collect($request->data()['messages']);
            $tool = $messages->firstWhere('role', 'tool');

            return $messages->where('role', 'user')->count() === 12
                && $tool !== null
                && mb_strlen($tool['content']) < 2000
                && str_contains($tool['content'], 'حذف شد')
                && ! $messages->contains(fn (array $message) => ($message['content'] ?? null) === 'سؤال 1');
        });
    }
}
