<?php

namespace Tests\Feature;

use App\Domain\Ai\AiSuggestedPrompts;
use App\Domain\Ai\MarkdownTables;
use App\Enums\AiMessageRole;
use App\Filament\Pages\AiAssistant;
use App\Filament\Resources\Bookings\BookingResource;
use App\Models\AiConversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AiAssistantConversationUxTest extends TestCase
{
    use RefreshDatabase;

    private const TABLE = "گزارش:\n\n| کارمند | دقیقه تأخیر |\n|---|---|\n| **رضا** | 15 |\n| سارا | 0 |\n\nجمع‌بندی.";

    private function actingAsAssistantUser(): User
    {
        $this->artisan('access-control:sync');

        $role = Role::create(['name' => 'کاربر دستیار '.uniqid()]);
        $role->givePermissionTo(Permission::whereIn('name', ['ai-assistant.access', 'ai-assistant.report'])->get());

        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);

        return $user;
    }

    public function test_markdown_tables_are_extracted_with_their_header(): void
    {
        $tables = MarkdownTables::extract(self::TABLE);

        $this->assertCount(1, $tables);
        $this->assertSame(['کارمند', 'دقیقه تأخیر'], $tables[0][0]);
        $this->assertSame(['رضا', '15'], $tables[0][1]);
        $this->assertCount(3, $tables[0]);
    }

    public function test_text_without_a_table_has_none(): void
    {
        $this->assertFalse(MarkdownTables::hasTable('فقط یک متن | با یک خط'));
        $this->assertFalse(MarkdownTables::hasTable(null));
        $this->assertTrue(MarkdownTables::hasTable(self::TABLE));
    }

    public function test_tables_are_written_to_a_workbook_file(): void
    {
        $path = MarkdownTables::toWorkbook(MarkdownTables::extract(self::TABLE));

        $this->assertFileExists($path);
        $this->assertGreaterThan(0, filesize($path));

        unlink($path);
    }

    public function test_suggestions_follow_the_last_module_and_fall_back_to_the_general_set(): void
    {
        $general = AiSuggestedPrompts::for(null);
        $this->assertCount(4, $general);

        $booking = AiSuggestedPrompts::for(['resource' => BookingResource::class, 'record_title' => 'GNJ-BKG-1']);

        $this->assertStringContainsString('GNJ-BKG-1', $booking[0]);
        $this->assertContains('نرخ اشغال ۷ روز آینده چقدر است؟', $booking);
    }

    public function test_feedback_toggles_on_my_own_answers_only(): void
    {
        $user = $this->actingAsAssistantUser();
        $mine = AiConversation::create(['user_id' => $user->id])->messages()->create(['role' => AiMessageRole::Assistant, 'content' => 'پاسخ']);
        $theirs = AiConversation::create(['user_id' => User::factory()->create()->id])->messages()->create(['role' => AiMessageRole::Assistant, 'content' => 'پاسخ']);

        $component = Livewire::test(AiAssistant::class)->call('setMessageFeedback', $mine->id, 'up');
        $this->assertSame('up', $mine->fresh()->feedback);

        $component->call('setMessageFeedback', $mine->id, 'up');
        $this->assertNull($mine->fresh()->feedback);

        $component->call('setMessageFeedback', $theirs->id, 'down')->call('setMessageFeedback', $mine->id, 'sideways');
        $this->assertNull($theirs->fresh()->feedback);
        $this->assertNull($mine->fresh()->feedback);
    }

    public function test_a_conversation_can_be_renamed_and_deleted_only_by_its_owner(): void
    {
        $user = $this->actingAsAssistantUser();
        $mine = AiConversation::create(['user_id' => $user->id, 'title' => 'قدیمی']);
        $theirs = AiConversation::create(['user_id' => User::factory()->create()->id, 'title' => 'دیگری']);

        Livewire::test(AiAssistant::class)
            ->call('renameConversation', $mine->id, 'گزارش حضور')
            ->call('renameConversation', $theirs->id, 'هک')
            ->call('renameConversation', $mine->id, '   ')
            ->call('deleteConversation', $theirs->id);

        $this->assertSame('گزارش حضور', $mine->fresh()->title);
        $this->assertSame('دیگری', $theirs->fresh()->title);

        Livewire::test(AiAssistant::class)->set('conversationId', $mine->id)->call('deleteConversation', $mine->id);

        $this->assertNull($mine->fresh());
        $this->assertNotNull($theirs->fresh());
    }

    public function test_the_conversation_list_can_be_searched_by_title(): void
    {
        $user = $this->actingAsAssistantUser();
        AiConversation::create(['user_id' => $user->id, 'title' => 'گزارش حضور و غیاب']);
        AiConversation::create(['user_id' => $user->id, 'title' => 'وضعیت پروژه‌ها']);

        $component = Livewire::test(AiAssistant::class);

        $this->assertCount(2, $component->instance()->getConversations());

        $component->set('conversationSearch', 'حضور');

        $this->assertSame(['گزارش حضور و غیاب'], $component->instance()->getConversations()->pluck('title')->all());
    }

    public function test_a_tables_answer_can_be_downloaded_as_excel(): void
    {
        $user = $this->actingAsAssistantUser();
        $message = AiConversation::create(['user_id' => $user->id])->messages()->create(['role' => AiMessageRole::Assistant, 'content' => self::TABLE]);

        Livewire::test(AiAssistant::class)
            ->call('downloadMessageTables', $message->id)
            ->assertFileDownloaded();
    }

    public function test_another_users_answer_cannot_be_downloaded(): void
    {
        $this->actingAsAssistantUser();
        $theirs = AiConversation::create(['user_id' => User::factory()->create()->id])->messages()->create(['role' => AiMessageRole::Assistant, 'content' => self::TABLE]);

        Livewire::test(AiAssistant::class)
            ->call('downloadMessageTables', $theirs->id)
            ->assertNoFileDownloaded();
    }
}
