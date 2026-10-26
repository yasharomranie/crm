<?php

namespace Database\Factories;

use App\Enums\DigitalProjectLogKind;
use App\Models\DigitalProject;
use App\Models\DigitalProjectLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DigitalProjectLog>
 */
class DigitalProjectLogFactory extends Factory
{
    protected $model = DigitalProjectLog::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'digital_project_id' => DigitalProject::factory(),
            'kind' => DigitalProjectLogKind::Done,
            'body' => 'گزارش '.fake()->unique()->numerify('####'),
            'logged_on' => today(),
        ];
    }
}
