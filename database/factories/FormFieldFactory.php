<?php

namespace Database\Factories;

use App\Enums\FormFieldType;
use App\Enums\TableColumnType;
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

    /**
     * A repeatable-table field with a couple of columns - the shape
     * DynamicFormFieldBuilder's ->table() Repeater and every value renderer
     * (detail view, print/word exports, mergedDocumentBody()) expect in
     * options.columns.
     */
    public function table(): static
    {
        return $this->state(fn () => [
            'field_type' => FormFieldType::Table,
            'options' => [
                'columns' => [
                    ['key' => 'unit_name', 'label' => 'نام واحد', 'type' => TableColumnType::Text->value],
                    ['key' => 'amount', 'label' => 'مبلغ (ریال)', 'type' => TableColumnType::Number->value],
                ],
                'default_rows' => 0,
            ],
        ]);
    }
}
