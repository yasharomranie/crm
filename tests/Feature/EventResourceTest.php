<?php

namespace Tests\Feature;

use App\Filament\Resources\Events\Pages\CreateEvent;
use App\Filament\Resources\Events\Pages\ListEvents;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventResourceTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));

        return $admin;
    }

    public function test_the_index_page_lists_events(): void
    {
        $event = Event::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(ListEvents::class)
            ->assertCanSeeTableRecords([$event]);
    }

    public function test_an_event_can_be_created_with_participants_and_defaults_to_the_creating_user(): void
    {
        $admin = $this->admin();
        $other = User::factory()->create();

        Livewire::actingAs($admin)
            ->test(CreateEvent::class)
            ->fillForm([
                'title' => 'جلسه هفتگی',
                'starts_at' => now()->addWeek(),
                'participants' => [$admin->id, $other->id],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $event = Event::where('title', 'جلسه هفتگی')->firstOrFail();

        $this->assertSame($admin->id, $event->created_by);
        $this->assertCount(2, $event->participants);
    }
}
