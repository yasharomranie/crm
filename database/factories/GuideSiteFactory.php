<?php

namespace Database\Factories;

use App\Models\GuideSite;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GuideSite>
 */
class GuideSiteFactory extends Factory
{
    protected $model = GuideSite::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['name' => 'مجموعه '.fake()->unique()->numerify('####'), 'is_active' => true];
    }
}
