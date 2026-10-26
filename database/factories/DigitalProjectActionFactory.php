<?php

namespace Database\Factories;

use App\Enums\DigitalProjectActionStatus;
use App\Models\DigitalProject;
use App\Models\DigitalProjectAction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DigitalProjectAction>
 */
class DigitalProjectActionFactory extends Factory
{
    protected $model = DigitalProjectAction::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'digital_project_id' => DigitalProject::factory(),
            'title' => 'اقدام '.fake()->unique()->numerify('####'),
            'status' => DigitalProjectActionStatus::Open,
        ];
    }
}
