<?php

namespace Tests\Feature\Crm;

use App\Filament\Resources\Guests\Pages\ViewGuest;
use App\Filament\Resources\Guests\RelationManagers\ActivitiesRelationManager;
use App\Models\Guest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GuestActivityTimelineTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));

        return $admin;
    }

    public function test_updating_a_guests_logged_field_automatically_records_an_activity(): void
    {
        $guest = Guest::factory()->create(['name' => 'نام قدیمی']);

        $guest->update(['name' => 'نام جدید']);

        $this->assertSame(1, $guest->activitiesAsSubject()->count());
        $this->assertSame('updated', $guest->activitiesAsSubject()->first()->event);
    }

    public function test_logging_a_manual_interaction_from_the_guest_page_records_it_against_the_guest(): void
    {
        $admin = $this->admin();
        $guest = Guest::factory()->create();

        Livewire::actingAs($admin)
            ->test(ViewGuest::class, ['record' => $guest->getKey()])
            ->mountAction('logCrmActivity')
            ->setActionData([
                'type' => 'call',
                'description' => 'با مشتری تماس گرفتم و قیمت را توضیح دادم.',
            ])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $activity = $guest->activitiesAsSubject()->where('event', 'call')->first();

        $this->assertNotNull($activity);
        $this->assertSame('با مشتری تماس گرفتم و قیمت را توضیح دادم.', $activity->description);
        $this->assertSame($admin->id, $activity->causer_id);
    }

    public function test_the_relation_manager_lists_logged_interactions_for_the_guest(): void
    {
        $admin = $this->admin();
        $guest = Guest::factory()->create();

        activity('crm_interactions')
            ->performedOn($guest)
            ->causedBy($admin)
            ->withProperties(['type' => 'note'])
            ->event('note')
            ->log('یادداشت آزمایشی');

        Livewire::actingAs($admin)
            ->test(ActivitiesRelationManager::class, [
                'ownerRecord' => $guest,
                'pageClass' => ViewGuest::class,
            ])
            ->assertCanSeeTableRecords($guest->activitiesAsSubject()->get());
    }
}
