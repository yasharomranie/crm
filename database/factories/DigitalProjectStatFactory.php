<?php

namespace Database\Factories;

use App\Enums\DigitalProjectMetric;
use App\Models\DigitalProject;
use App\Models\DigitalProjectStat;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DigitalProjectStat>
 */
class DigitalProjectStatFactory extends Factory
{
    protected $model = DigitalProjectStat::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'digital_project_id' => DigitalProject::factory(),
            'metric' => DigitalProjectMetric::Followers,
            'value' => 1000,
            'recorded_on' => today(),
        ];
    }
}
