<?php

namespace Database\Factories;

use App\Models\WebAppBanner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebAppBanner>
 */
class WebAppBannerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(3),
            'subtitle' => $this->faker->sentence(6),
            'image_path' => 'web-app/banners/'.$this->faker->uuid().'.jpg',
            'link_url' => null,
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
