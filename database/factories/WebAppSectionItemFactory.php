<?php

namespace Database\Factories;

use App\Enums\WebAppTileAccent;
use App\Models\WebAppSection;
use App\Models\WebAppSectionItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebAppSectionItem>
 */
class WebAppSectionItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'web_app_section_id' => WebAppSection::factory(),
            'label' => $this->faker->words(2, true),
            'icon_path' => null,
            'accent' => $this->faker->randomElement(WebAppTileAccent::cases()),
            'link_url' => null,
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
