<?php

namespace Database\Factories;

use App\Enums\GuideReferenceStatus;
use App\Models\GuideObject;
use App\Models\GuideReferenceImage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GuideReferenceImage>
 */
class GuideReferenceImageFactory extends Factory
{
    protected $model = GuideReferenceImage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['guide_object_id' => GuideObject::factory(), 'path' => 'guide/references/'.fake()->uuid().'.jpg', 'status' => GuideReferenceStatus::Pending];
    }

    /**
     * @param  list<float>  $embedding
     */
    public function processed(array $embedding = [1.0, 0.0, 0.0]): static
    {
        return $this->state(fn () => ['status' => GuideReferenceStatus::Processed, 'embedding' => $embedding, 'processed_at' => now()]);
    }
}
