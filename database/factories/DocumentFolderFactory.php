<?php

namespace Database\Factories;

use App\Models\DocumentFolder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentFolder>
 */
class DocumentFolderFactory extends Factory
{
    protected $model = DocumentFolder::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
        ];
    }
}
