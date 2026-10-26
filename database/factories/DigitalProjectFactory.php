<?php

namespace Database\Factories;

use App\Enums\DigitalProjectChannel;
use App\Enums\DigitalProjectStatus;
use App\Models\DigitalProject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DigitalProject>
 */
class DigitalProjectFactory extends Factory
{
    protected $model = DigitalProject::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'channel' => DigitalProjectChannel::Website,
            'name' => 'پروژه '.fake()->unique()->numerify('####'),
            'handle' => 'https://'.fake()->domainName(),
            'description' => fake()->sentence(12),
            'status' => DigitalProjectStatus::Active,
        ];
    }
}
