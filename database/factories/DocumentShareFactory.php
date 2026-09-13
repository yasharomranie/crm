<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\DocumentShare;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentShare>
 */
class DocumentShareFactory extends Factory
{
    protected $model = DocumentShare::class;

    public function definition(): array
    {
        return [
            'document_id' => Document::factory(),
            'user_id' => User::factory(),
        ];
    }
}
