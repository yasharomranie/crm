<?php

namespace Database\Factories;

use App\Enums\DealSource;
use App\Models\LeadCaptureForm;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeadCaptureForm>
 */
class LeadCaptureFormFactory extends Factory
{
    protected $model = LeadCaptureForm::class;

    public function definition(): array
    {
        return [
            'name' => 'فرم '.fake()->word(),
            'slug' => fake()->unique()->bothify('form-########'),
            'title' => 'درخواست مشاوره رایگان',
            'description' => 'شماره‌ی خود را بگذارید تا کارشناسان ما با شما تماس بگیرند.',
            'success_message' => 'درخواست شما ثبت شد. به‌زودی با شما تماس می‌گیریم.',
            'deal_source' => DealSource::Online,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
