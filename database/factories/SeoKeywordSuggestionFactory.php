<?php

namespace Database\Factories;

use App\Enums\SeoSearchIntent;
use App\Models\SeoKeywordSuggestion;
use App\Models\SeoProject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SeoKeywordSuggestion>
 */
class SeoKeywordSuggestionFactory extends Factory
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
            'keyword' => $this->faker->words(3, true),
            'intent' => SeoSearchIntent::Informational,
            'suggested_content_type' => 'مقاله وبلاگ',
            'reason' => $this->faker->sentence(),
        ];
    }
}
