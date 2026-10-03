<?php

namespace Tests\Feature\Seo;

use App\Domain\Seo\SeoAnalystPersona;
use App\Models\SeoProject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoAnalystPersonaTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_prompt_is_grounded_in_the_projects_own_business_context(): void
    {
        $project = SeoProject::factory()->create([
            'brand' => 'گنجنامه',
            'real_services' => 'تله‌کابین، اجاره ویلا',
        ]);

        $prompt = SeoAnalystPersona::systemPromptFor($project);

        $this->assertStringContainsString('گنجنامه', $prompt);
        $this->assertStringContainsString('تله‌کابین، اجاره ویلا', $prompt);
        $this->assertStringContainsString('مشاهده', $prompt);
        $this->assertStringContainsString('فرضیه', $prompt);
    }
}
