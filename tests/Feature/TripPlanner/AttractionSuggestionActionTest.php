<?php

namespace Tests\Feature\TripPlanner;

use App\Filament\Resources\Attractions\Pages\ListAttractions;
use App\Models\Attraction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AttractionSuggestionActionTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));

        return $admin;
    }

    public function test_it_creates_inactive_draft_attractions_without_coordinates_from_the_reviewed_suggestions(): void
    {
        Livewire::actingAs($this->admin())
            ->test(ListAttractions::class)
            ->callAction('suggestAttractions', data: [
                'focus' => null,
                'count' => 2,
                'suggestions' => [
                    ['import' => true, 'name' => 'روستای ورکانه', 'category' => 'historical', 'address' => 'همدان', 'avg_visit_minutes' => 90, 'opens_at' => '08:00', 'closes_at' => '18:00', 'description' => 'توضیح'],
                    ['import' => false, 'name' => 'جایی که نمی‌خواهیم', 'category' => 'nature', 'address' => null, 'avg_visit_minutes' => 60, 'opens_at' => null, 'closes_at' => null, 'description' => null],
                ],
            ]);

        $this->assertSame(1, Attraction::count());

        $attraction = Attraction::firstOrFail();
        $this->assertSame('روستای ورکانه', $attraction->name);
        $this->assertFalse($attraction->is_active);
        $this->assertNull($attraction->lat);
        $this->assertNull($attraction->lng);
        $this->assertNotEmpty($attraction->slug);
    }

    public function test_it_skips_a_suggestion_whose_name_already_exists(): void
    {
        Attraction::factory()->create(['name' => 'غار علی‌صدر']);

        Livewire::actingAs($this->admin())
            ->test(ListAttractions::class)
            ->callAction('suggestAttractions', data: [
                'focus' => null,
                'count' => 1,
                'suggestions' => [
                    ['import' => true, 'name' => 'غار علی‌صدر', 'category' => 'nature', 'address' => null, 'avg_visit_minutes' => 60, 'opens_at' => null, 'closes_at' => null, 'description' => null],
                ],
            ]);

        $this->assertSame(1, Attraction::count());
    }

    public function test_it_does_not_create_anything_when_nothing_is_selected_for_import(): void
    {
        Livewire::actingAs($this->admin())
            ->test(ListAttractions::class)
            ->callAction('suggestAttractions', data: [
                'focus' => null,
                'count' => 1,
                'suggestions' => [
                    ['import' => false, 'name' => 'جای نخواسته', 'category' => 'nature', 'address' => null, 'avg_visit_minutes' => 60, 'opens_at' => null, 'closes_at' => null, 'description' => null],
                ],
            ]);

        $this->assertSame(0, Attraction::count());
    }
}
