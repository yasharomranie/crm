<?php

namespace Database\Factories;

use App\Models\GuideObject;
use App\Models\GuideVideo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GuideVideo>
 */
class GuideVideoFactory extends Factory
{
    protected $model = GuideVideo::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['guide_object_id' => GuideObject::factory(), 'title' => 'داستان '.fake()->unique()->numerify('##'), 'url' => 'https://example.test/video.mp4', 'language' => 'fa', 'is_published' => true];
    }
}
