<?php

namespace Database\Factories;

use App\Models\FormField;
use App\Models\FormSubmission;
use App\Models\FormSubmissionValue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FormSubmissionValue>
 */
class FormSubmissionValueFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'form_submission_id' => FormSubmission::factory(),
            'form_field_id' => FormField::factory(),
            'value_text' => fake()->sentence(),
            'value_number' => null,
            'value_date' => null,
            'value_json' => null,
            'file_path' => null,
        ];
    }
}
