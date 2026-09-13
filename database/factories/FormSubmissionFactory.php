<?php

namespace Database\Factories;

use App\Enums\FormSubmissionStatus;
use App\Models\Department;
use App\Models\FormSubmission;
use App\Models\FormTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FormSubmission>
 */
class FormSubmissionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'form_template_id' => FormTemplate::factory(),
            'form_template_version' => 1,
            'submitted_by' => User::factory(),
            'department_id' => Department::factory(),
            'status' => FormSubmissionStatus::Draft,
            'submitted_at' => now(),
        ];
    }
}
