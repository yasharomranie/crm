<?php

namespace Database\Factories;

use App\Models\GuideSite;
use App\Models\GuideZone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GuideZone>
 */
class GuideZoneFactory extends Factory
{
    protected $model = GuideZone::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['guide_site_id' => GuideSite::factory(), 'name' => 'تالار '.fake()->unique()->numerify('##'), 'sort_order' => 0];
    }
}
