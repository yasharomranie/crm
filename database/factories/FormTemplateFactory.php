<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\FormTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FormTemplate>
 */
class FormTemplateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->unique()->sentence(3),
            'description' => fake()->sentence(),
            'department_id' => Department::factory(),
            'workflow_definition_id' => null,
            'version' => 1,
            'is_active' => true,
            'created_by' => User::factory(),
        ];
    }
}
