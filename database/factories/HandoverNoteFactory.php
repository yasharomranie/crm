<?php

namespace Database\Factories;

use App\Models\HandoverNote;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HandoverNote>
 */
class HandoverNoteFactory extends Factory
{
    protected $model = HandoverNote::class;

    public function definition(): array
    {
        return [
            'body' => fake()->sentence(),
        ];
    }
}
