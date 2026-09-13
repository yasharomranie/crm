<?php

namespace Database\Factories;

use App\Enums\FormFieldType;
use App\Models\FormField;
use App\Models\FormTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FormField>
 */
class FormFieldFactory extends Factory
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
            'field_key' => fake()->unique()->slug(2, false),
            'label' => fake()->words(3, true),
            'field_type' => FormFieldType::Text,
            'options' => null,
            'validation_rules' => null,
            'conditional_logic' => null,
            'sort_order' => 0,
            'is_required' => false,
        ];
    }
}
