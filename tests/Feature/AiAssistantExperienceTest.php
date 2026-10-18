<?php

namespace Tests\Feature;

use App\Enums\AiMessageRole;
use App\Enums\BookingStatus;
use App\Filament\Pages\AiAssistant;
use App\Livewire\AiAssistantWidget;
use App\Models\AiConversation;
use App\Models\AiProvider;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AiAssistantExperienceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * @param  list<string>  $permissions
     */
    private function actingAsUserWith(array $permissions = []): User
    {
        $this->artisan('access-control:sync');

        $role = Role::create(['name' => 'نقش '.uniqid()]);
        $role->givePermissionTo(Permission::whereIn('name', ['ai-assistant.access', 'ai-assistant.report', ...$permissions])->get());

        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);

        return $user;
    }

    private function fakeToolCallThenAnswer(string $tool, array $arguments): void
    {
        Http::fakeSequence()
            ->push(['choices' => [['message' => [
                'role' => 'assistant',
                'content' => null,
                'tool_calls' => [['id' => 'c1', 'type' => 'function', 'function' => ['name' => $tool, 'arguments' => json_encode($arguments)]]],
            ]]]])
            ->push(['choices' => [['message' => ['role' => 'assistant', 'content' => 'تمام.']]]]);
    }

    private function toolPayload(): array
    {
        return json_decode(AiConversation::sole()->messages->firstWhere('role', AiMessageRole::Tool)->content, true);
    }

    public function test_a_conversation_can_be_pinned_and_pinned_ones_come_first(): void
    {
        $user = $this->actingAsUserWith();
        $old = AiConversation::create(['user_id' => $user->id, 'title' => 'قدیمی']);
        $old->forceFill(['updated_at' => now()->subDays(3)])->saveQuietly();
        $recent = AiConversation::create(['user_id' => $user->id, 'title' => 'تازه']);

        $component = Livewire::test(AiAssistant::class);
        $this->assertSame(['تازه', 'قدیمی'], $component->instance()->getConversations()->pluck('title')->all());

        $component->call('togglePinConversation', $old->id);

        $this->assertSame(['قدیمی', 'تازه'], $component->instance()->getConversations()->pluck('title')->all());
        $this->assertNotNull($old->fresh()->pinned_at);

        $component->call('togglePinConversation', $old->id);
        $this->assertNull($old->fresh()->pinned_at);
        $this->assertNotNull($recent->fresh());
    }

    public function test_archived_conversations_are_hidden_until_asked_for(): void
    {
        $user = $this->actingAsUserWith();
        $keep = AiConversation::create(['user_id' => $user->id, 'title' => 'فعال']);
        $archived = AiConversation::create(['user_id' => $user->id, 'title' => 'بایگانی']);

        $component = Livewire::test(AiAssistant::class)->set('conversationId', $archived->id)->call('toggleArchiveConversation', $archived->id);

        $this->assertNotNull($archived->fresh()->archived_at);
        $this->assertSame(['فعال'], $component->instance()->getConversations()->pluck('title')->all());
        $this->assertSame($keep->id, $component->instance()->conversationId);

        $component->set('showArchived', true);
        $this->assertCount(2, $component->instance()->getConversations());

        $component->call('toggleArchiveConversation', $archived->id);
        $this->assertNull($archived->fresh()->archived_at);
    }

    public function test_another_users_conversation_cannot_be_pinned_or_archived(): void
    {
        $this->actingAsUserWith();
        $theirs = AiConversation::create(['user_id' => User::factory()->create()->id]);

        Livewire::test(AiAssistant::class)
            ->call('togglePinConversation', $theirs->id)
            ->call('toggleArchiveConversation', $theirs->id);

        $this->assertNull($theirs->fresh()->pinned_at);
        $this->assertNull($theirs->fresh()->archived_at);
    }

    public function test_a_conversation_prints_for_its_owner_with_html_stripped(): void
    {
        $user = $this->actingAsUserWith();
        $conversation = AiConversation::create(['user_id' => $user->id, 'title' => 'گزارش هفته']);
        $conversation->messages()->create(['role' => AiMessageRole::User, 'content' => 'سلام']);
        $conversation->messages()->create(['role' => AiMessageRole::Assistant, 'content' => 'گزارش <script>alert(1)</script> **آماده** است']);
        $conversation->messages()->create(['role' => AiMessageRole::Tool, 'content' => '{"secret":1}', 'tool_call_id' => 'x']);

        $this->get(route('ai-assistant.conversation.print', $conversation))
            ->assertOk()
            ->assertSee('گزارش هفته')
            ->assertSee('<strong>آماده</strong>', false)
            ->assertDontSee('<script>alert', false)
            ->assertDontSee('secret');
    }

    public function test_someone_elses_conversation_does_not_print(): void
    {
        $this->actingAsUserWith();
        $theirs = AiConversation::create(['user_id' => User::factory()->create()->id]);

        $this->get(route('ai-assistant.conversation.print', $theirs))->assertNotFound();
    }

    public function test_printing_needs_access_to_the_assistant(): void
    {
        $user = User::factory()->create();
        $this->artisan('access-control:sync');
        $role = Role::create(['name' => 'بدون دستیار']);
        $role->givePermissionTo(Permission::where('name', 'tasks.view_any')->get());
        $user->assignRole($role);
        $this->actingAs($user);
        $conversation = AiConversation::create(['user_id' => $user->id]);

        $this->get(route('ai-assistant.conversation.print', $conversation))->assertForbidden();
    }

    public function test_a_report_can_cover_a_custom_jalali_date_range(): void
    {
        AiProvider::factory()->active()->create();
        Carbon::setTestNow('2026-12-01 12:00:00');
        Booking::factory()->create(['created_at' => '2026-10-05 10:00:00']);
        Booking::factory()->create(['created_at' => '2026-10-05 11:00:00', 'status' => BookingStatus::Cancelled]);
        Booking::factory()->create(['created_at' => '2026-11-20 10:00:00']);
        // 1405/07/01 .. 1405/07/30 = 23 Sep .. 22 Oct 2026
        $this->fakeToolCallThenAnswer('get_cancellation_summary', ['from_date' => '1405/07/01', 'to_date' => '1405/07/30']);
        $this->actingAsUserWith(['bookings.view_any']);

        Livewire::test(AiAssistant::class)->set('body', 'نرخ کنسلی مهر')->call('send');

        $payload = $this->toolPayload();

        $this->assertSame('از 1405/07/01 تا 1405/07/30', $payload['period']);
        $this->assertSame(2, $payload['bookings_created']);
        $this->assertSame(1, $payload['cancelled_count']);
    }

    public function test_an_invalid_or_oversized_range_falls_back_to_the_month(): void
    {
        AiProvider::factory()->active()->create();
        $this->fakeToolCallThenAnswer('get_cancellation_summary', ['from_date' => '1403/01/01', 'to_date' => '1405/12/29']);
        $this->actingAsUserWith(['bookings.view_any']);

        Livewire::test(AiAssistant::class)->set('body', 'نرخ کنسلی')->call('send');

        $this->assertSame('این ماه', $this->toolPayload()['period']);
    }

    public function test_search_results_carry_a_link_to_the_record(): void
    {
        AiProvider::factory()->active()->create();
        $booking = Booking::factory()->create();
        $this->fakeToolCallThenAnswer('search_bookings', []);
        $this->actingAsUserWith(['bookings.view_any']);

        Livewire::test(AiAssistant::class)->set('body', 'رزروها')->call('send');

        $this->assertStringContainsString("/bookings/{$booking->id}", $this->toolPayload()['bookings'][0]['link']);
    }

    public function test_a_refusal_names_the_part_of_the_system_and_who_can_grant_it(): void
    {
        AiProvider::factory()->active()->create();
        $this->fakeToolCallThenAnswer('get_attendance_summary', []);
        $this->actingAsUserWith();

        Livewire::test(AiAssistant::class)->set('body', 'وضعیت حضور؟')->call('send');

        $this->assertStringContainsString('«حضور و غیاب»', $this->toolPayload()['error']);
        $this->assertStringContainsString('مدیر سیستم', $this->toolPayload()['error']);
    }

    public function test_the_cancellation_report_gets_a_chart(): void
    {
        AiProvider::factory()->active()->create();
        Booking::factory()->create(['status' => BookingStatus::Cancelled]);
        $this->fakeToolCallThenAnswer('get_cancellation_summary', ['period' => 'this_month']);
        $this->actingAsUserWith(['bookings.view_any']);

        Livewire::test(AiAssistant::class)->set('body', 'نرخ کنسلی')->call('send');

        $answer = AiConversation::sole()->messages->where('role', AiMessageRole::Assistant)->last();

        $this->assertSame('doughnut', $answer->chart['type']);
    }

    public function test_the_widget_offers_starter_questions_and_message_actions(): void
    {
        $user = $this->actingAsUserWith();
        $conversation = AiConversation::create(['user_id' => $user->id]);
        $conversation->messages()->create(['role' => AiMessageRole::User, 'content' => 'سلام']);
        $conversation->messages()->create(['role' => AiMessageRole::Assistant, 'content' => "| الف | ب |\n|---|---|\n| 1 | 2 |"]);

        Livewire::test(AiAssistantWidget::class)
            ->assertSee('پاسخ خوبی بود')
            ->assertSee('دانلود جدول‌ها به Excel');

        $conversation->delete();

        Livewire::test(AiAssistantWidget::class)->assertSee('وضعیت کلی امروز رو خلاصه کن');
    }

    public function test_the_page_shows_archive_and_print_controls(): void
    {
        $user = $this->actingAsUserWith();
        AiConversation::create(['user_id' => $user->id, 'title' => 'گزارش']);

        Livewire::test(AiAssistant::class)
            ->assertSee('نمایش گفتگوهای آرشیوشده')
            ->assertSee('سنجاق کردن')
            ->assertSee('آرشیو');
    }
}
