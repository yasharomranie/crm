<?php

namespace Database\Factories;

use App\Models\AiProvider;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiProvider>
 */
class AiProviderFactory extends Factory
{
    protected $model = AiProvider::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'base_url' => 'https://api.example.test/v1',
            'api_key' => fake()->uuid(),
            'default_model' => 'gpt-4o-mini',
            'is_active' => false,
        ];
    }

    public function active(): self
    {
        return $this->state(['is_active' => true]);
    }
}
