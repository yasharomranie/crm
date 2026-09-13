<?php

namespace Database\Factories;

use App\Enums\ApproverType;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowStep;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkflowStep>
 */
class WorkflowStepFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workflow_definition_id' => WorkflowDefinition::factory(),
            'step_order' => 1,
            'name' => fake()->words(2, true),
            'approver_type' => ApproverType::SpecificUser,
            'approver_role_id' => null,
            'approver_user_id' => null,
            'sla_hours' => 24,
            'condition_rules' => null,
        ];
    }
}
