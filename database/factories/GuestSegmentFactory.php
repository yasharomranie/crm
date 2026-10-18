<?php

namespace Database\Factories;

use App\Models\GuestSegment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GuestSegment>
 */
class GuestSegmentFactory extends Factory
{
    protected $model = GuestSegment::class;

    public function definition(): array
    {
        return [
            'name' => 'بخش '.fake()->word(),
            'match_type' => 'all',
            'conditions' => [['type' => 'has_mobile', 'answer' => 'yes']],
        ];
    }
}
