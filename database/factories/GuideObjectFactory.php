<?php

namespace Database\Factories;

use App\Enums\GuideObjectCategory;
use App\Models\GuideObject;
use App\Models\GuideSite;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GuideObject>
 */
class GuideObjectFactory extends Factory
{
    protected $model = GuideObject::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'guide_site_id' => GuideSite::factory(),
            'title_fa' => 'شیء '.fake()->unique()->numerify('####'),
            'category' => GuideObjectCategory::Statue3d,
            'description' => 'توضیح کوتاه دربارهٔ این اثر.',
            'is_active' => true,
        ];
    }
}
