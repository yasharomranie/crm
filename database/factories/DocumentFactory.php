<?php

namespace Database\Factories;

use App\Enums\DocumentAccessLevel;
use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    protected $model = Document::class;

    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'file_path' => 'documents/'.fake()->uuid().'.pdf',
            'mime_type' => 'application/pdf',
            'size_kb' => fake()->numberBetween(10, 5000),
            'access_level' => DocumentAccessLevel::PublicInternal,
            'uploaded_by' => User::factory(),
        ];
    }

    public function departmentOnly(int $departmentId): self
    {
        return $this->state([
            'access_level' => DocumentAccessLevel::DepartmentOnly,
            'department_id' => $departmentId,
        ]);
    }

    public function restricted(): self
    {
        return $this->state([
            'access_level' => DocumentAccessLevel::Restricted,
        ]);
    }
}
