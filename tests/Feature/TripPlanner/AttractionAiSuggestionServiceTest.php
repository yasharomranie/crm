<?php

namespace Tests\Feature\TripPlanner;

use App\Domain\TripPlanner\AttractionAiSuggestionService;
use App\Domain\TripPlanner\AttractionSuggestionException;
use App\Enums\AttractionCategory;
use App\Exceptions\AiProviderException;
use App\Models\AiProvider;
use App\Models\Attraction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AttractionAiSuggestionServiceTest extends TestCase
{
    use RefreshDatabase;

    private function activeProvider(): AiProvider
    {
        return AiProvider::create([
            'name' => 'Test Provider',
            'base_url' => 'https://api.test-provider.example/v1',
            'api_key' => 'test-key',
            'default_model' => 'gpt-test',
            'is_active' => true,
        ]);
    }

    private function fakeChatResponse(array $content): void
    {
        Http::fake([
            'api.test-provider.example/*' => Http::response([
                'choices' => [['message' => ['content' => json_encode($content, JSON_UNESCAPED_UNICODE)]]],
            ]),
        ]);
    }

    private function service(): AttractionAiSuggestionService
    {
        return app(AttractionAiSuggestionService::class);
    }

    public function test_it_throws_when_no_ai_provider_is_active(): void
    {
        $this->expectException(AiProviderException::class);

        $this->service()->suggestMany(5);
    }

    public function test_it_returns_valid_suggestions_from_the_ai_response(): void
    {
        $this->activeProvider();

        $this->fakeChatResponse([
            ['name' => 'روستای ورکانه', 'category' => 'historical', 'description' => 'توضیح', 'address' => 'همدان', 'avg_visit_minutes' => 90, 'opens_at' => '08:00', 'closes_at' => '18:00'],
            ['name' => 'دریاچه اکباتان', 'category' => 'nature', 'description' => null, 'address' => null, 'avg_visit_minutes' => 60, 'opens_at' => null, 'closes_at' => null],
        ]);

        $items = $this->service()->suggestMany(2);

        $this->assertCount(2, $items);
        $this->assertSame('روستای ورکانه', $items[0]['name']);
        $this->assertSame(AttractionCategory::Historical->value, $items[0]['category']);
        $this->assertSame(90, $items[0]['avg_visit_minutes']);
        $this->assertNull($items[1]['opens_at']);
    }

    public function test_it_filters_out_suggestions_that_already_exist(): void
    {
        $this->activeProvider();
        Attraction::factory()->create(['name' => 'غار علی‌صدر']);

        $this->fakeChatResponse([
            ['name' => 'غار علی‌صدر', 'category' => 'nature', 'avg_visit_minutes' => 60],
            ['name' => 'جای جدید', 'category' => 'nature', 'avg_visit_minutes' => 60],
        ]);

        $items = $this->service()->suggestMany(2);

        $this->assertCount(1, $items);
        $this->assertSame('جای جدید', $items[0]['name']);
    }

    public function test_it_defaults_an_invalid_category_to_historical(): void
    {
        $this->activeProvider();

        $this->fakeChatResponse([
            ['name' => 'جای عجیب', 'category' => 'not-a-real-category', 'avg_visit_minutes' => 60],
        ]);

        $items = $this->service()->suggestMany(1);

        $this->assertSame(AttractionCategory::Historical->value, $items[0]['category']);
    }

    public function test_it_clamps_avg_visit_minutes_to_a_realistic_range(): void
    {
        $this->activeProvider();

        $this->fakeChatResponse([
            ['name' => 'جای خیلی کوتاه', 'category' => 'nature', 'avg_visit_minutes' => 2],
            ['name' => 'جای خیلی طولانی', 'category' => 'nature', 'avg_visit_minutes' => 5000],
        ]);

        $items = $this->service()->suggestMany(2);

        $this->assertSame(15, $items[0]['avg_visit_minutes']);
        $this->assertSame(240, $items[1]['avg_visit_minutes']);
    }

    public function test_it_throws_when_the_ai_response_is_not_valid_json(): void
    {
        $this->activeProvider();

        Http::fake([
            'api.test-provider.example/*' => Http::response([
                'choices' => [['message' => ['content' => 'این یک متن آزاد است، نه JSON.']]],
            ]),
        ]);

        $this->expectException(AttractionSuggestionException::class);

        $this->service()->suggestMany(3);
    }
}
