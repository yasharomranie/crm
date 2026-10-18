<?php

namespace Tests\Feature\TripPlanner;

use App\Filament\Resources\Attractions\Pages\EditAttraction;
use App\Filament\Resources\Attractions\Pages\ListAttractions;
use App\Models\Attraction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AttractionAdminImprovementsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));

        return $admin;
    }

    public function test_ai_suggested_places_are_marked_as_such_and_start_unverified(): void
    {
        Livewire::actingAs($this->admin())
            ->test(ListAttractions::class)
            ->callAction('suggestAttractions', data: [
                'focus' => null,
                'count' => 1,
                'suggestions' => [
                    ['import' => true, 'name' => 'روستای ورکانه', 'category' => 'historical', 'address' => null, 'avg_visit_minutes' => 90, 'opens_at' => null, 'closes_at' => null, 'description' => null],
                ],
            ]);

        $attraction = Attraction::firstOrFail();

        $this->assertSame('ai_suggested', $attraction->source);
        $this->assertNull($attraction->verified_at);
    }

    public function test_the_unverified_filter_lists_only_unreviewed_ai_suggestions(): void
    {
        $unverified = Attraction::factory()->create(['source' => 'ai_suggested', 'verified_at' => null]);
        $verified = Attraction::factory()->create(['source' => 'ai_suggested', 'verified_at' => now()]);
        $manual = Attraction::factory()->create(['source' => 'manual']);

        Livewire::actingAs($this->admin())
            ->test(ListAttractions::class)
            ->filterTable('unverified')
            ->assertCanSeeTableRecords([$unverified])
            ->assertCanNotSeeTableRecords([$verified, $manual]);
    }

    public function test_verifying_an_ai_suggestion_stamps_the_time_and_unlocks_activation(): void
    {
        $attraction = Attraction::factory()->create(['source' => 'ai_suggested', 'verified_at' => null, 'is_active' => false]);

        Livewire::actingAs($this->admin())
            ->test(EditAttraction::class, ['record' => $attraction->getRouteKey()])
            ->fillForm(['verified' => true])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertNotNull($attraction->fresh()->verified_at);
    }

    public function test_the_ai_note_is_read_only_without_trip_planner_settings_access(): void
    {
        $this->artisan('access-control:sync');
        $role = Role::create(['name' => 'ویرایشگر جاذبه']);
        $role->givePermissionTo(Permission::whereIn('name', ['attractions.view_any', 'attractions.view', 'attractions.update'])->get());
        $editor = User::factory()->create();
        $editor->assignRole($role);
        $attraction = Attraction::factory()->create(['admin_note' => 'یادداشت اصلی']);

        Livewire::actingAs($editor)
            ->test(EditAttraction::class, ['record' => $attraction->getRouteKey()])
            ->assertFormFieldIsDisabled('admin_note');

        Livewire::actingAs($this->admin())
            ->test(EditAttraction::class, ['record' => $attraction->getRouteKey()])
            ->assertFormFieldIsEnabled('admin_note');
    }

    public function test_weekly_closed_days_and_new_details_are_saved(): void
    {
        $attraction = Attraction::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(EditAttraction::class, ['record' => $attraction->getRouteKey()])
            ->fillForm(['closed_weekdays' => [5], 'entry_fee' => 50000, 'kid_friendly' => true])
            ->call('save')
            ->assertHasNoFormErrors();

        $fresh = $attraction->fresh();
        $this->assertSame([5], $fresh->closed_weekdays);
        $this->assertSame(50000, $fresh->entry_fee);
        $this->assertTrue($fresh->kid_friendly);
    }
}
