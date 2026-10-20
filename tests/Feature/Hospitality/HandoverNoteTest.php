<?php

namespace Tests\Feature\Hospitality;

use App\Filament\Resources\HandoverNotes\Pages\ManageHandoverNotes;
use App\Models\HandoverNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HandoverNoteTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));

        return $admin;
    }

    public function test_staff_add_a_note_and_the_author_is_recorded(): void
    {
        $admin = $this->admin();

        Livewire::actingAs($admin)
            ->test(ManageHandoverNotes::class)
            ->callAction('create', ['body' => 'تاکسی ساعت ۶ برای اتاق ۱۲'])
            ->assertHasNoActionErrors();

        $note = HandoverNote::sole();
        $this->assertSame($admin->id, $note->created_by);
        $this->assertTrue($note->isOpen());
    }

    public function test_the_next_shift_resolves_a_note_once(): void
    {
        $admin = $this->admin();
        $note = HandoverNote::factory()->create();

        Livewire::actingAs($admin)
            ->test(ManageHandoverNotes::class)
            ->assertCanSeeTableRecords([$note])
            ->callTableAction('resolve', $note);

        $note->refresh();
        $this->assertFalse($note->isOpen());
        $this->assertSame($admin->id, $note->resolved_by);

        $resolvedAt = $note->resolved_at;
        $note->resolve(User::factory()->create()->id);
        $this->assertTrue($resolvedAt->equalTo($note->fresh()->resolved_at));
    }

    public function test_resolved_notes_are_hidden_by_default(): void
    {
        $open = HandoverNote::factory()->create();
        $done = HandoverNote::factory()->create(['resolved_at' => now()]);

        Livewire::actingAs($this->admin())
            ->test(ManageHandoverNotes::class)
            ->assertCanSeeTableRecords([$open])
            ->assertCanNotSeeTableRecords([$done]);
    }

    public function test_the_page_is_forbidden_without_permission(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::create(['name' => 'بدون دسترسی تحویل شیفت']));

        Livewire::actingAs($user)->test(ManageHandoverNotes::class)->assertForbidden();
    }
}
