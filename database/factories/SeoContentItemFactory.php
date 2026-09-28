<?php

namespace Database\Factories;

use App\Enums\SeoContentItemStatus;
use App\Models\SeoContentItem;
use App\Models\SeoProject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SeoContentItem>
 */
class SeoContentItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'seo_project_id' => SeoProject::factory(),
            'title' => $this->faker->sentence(6),
            'target_keyword' => $this->faker->words(3, true),
            'content_type' => 'مقاله وبلاگ',
            'status' => SeoContentItemStatus::Idea,
        ];
    }
}
