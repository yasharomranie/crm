<?php

namespace Database\Factories;

use App\Enums\WorkflowInstanceStatus;
use App\Models\FormSubmission;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowInstance;
use App\Models\WorkflowStep;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkflowInstance>
 */
class WorkflowInstanceFactory extends Factory
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
            'subject_id' => FormSubmission::factory(),
            'subject_type' => FormSubmission::class,
            'current_step_id' => WorkflowStep::factory(),
            'status' => WorkflowInstanceStatus::InProgress,
            'started_at' => now(),
            'completed_at' => null,
        ];
    }
}
