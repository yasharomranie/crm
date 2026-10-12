<?php

namespace Tests\Feature;

use App\Domain\Ai\KnowledgeNoteExtractor;
use App\Models\AiAssistantProfile;
use App\Models\AiProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class KnowledgeNoteExtractorTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_extracts_notes_from_a_short_document_in_one_call(): void
    {
        $provider = AiProvider::factory()->active()->create();
        $profile = AiAssistantProfile::factory()->create();

        Http::fake([
            '*/chat/completions' => Http::response([
                'choices' => [[
                    'message' => [
                        'role' => 'assistant',
                        'content' => json_encode(['نرخ کمیسیون فروش ۵ درصد است.', 'ساعت کاری دفتر ۹ تا ۱۷ است.']),
                    ],
                ]],
            ]),
        ]);

        $result = app(KnowledgeNoteExtractor::class)->extract($profile, $provider, 'نرخ کمیسیون فروش ۵ درصد است. ساعت کاری دفتر ۹ تا ۱۷ است.');

        Http::assertSentCount(1);
        $this->assertNull($result['error']);
        $this->assertCount(2, $result['notes']);
    }

    public function test_it_walks_a_long_document_in_multiple_chunks_and_deduplicates_notes(): void
    {
        $provider = AiProvider::factory()->active()->create();
        $profile = AiAssistantProfile::factory()->create();

        Http::fake([
            '*/chat/completions' => Http::response([
                'choices' => [[
                    'message' => [
                        'role' => 'assistant',
                        'content' => json_encode(['نکتهٔ تکراری در هر تکه']),
                    ],
                ]],
            ]),
        ]);

        $result = app(KnowledgeNoteExtractor::class)->extract($profile, $provider, str_repeat('قیمت متفاوت است. ', 5000));

        Http::assertSentCount(3);
        $this->assertNull($result['error']);
        $this->assertSame(['نکتهٔ تکراری در هر تکه'], $result['notes']);
    }

    public function test_it_stops_and_reports_the_error_when_the_provider_call_fails(): void
    {
        $provider = AiProvider::factory()->active()->create();
        $profile = AiAssistantProfile::factory()->create();

        Http::fake(['*/chat/completions' => Http::response(['error' => ['message' => 'سرویس در دسترس نیست.']], 500)]);

        $result = app(KnowledgeNoteExtractor::class)->extract($profile, $provider, 'متن کوتاه.');

        $this->assertSame([], $result['notes']);
        $this->assertNotNull($result['error']);
    }

    public function test_it_returns_no_notes_and_no_error_when_the_provider_finds_nothing_useful(): void
    {
        $provider = AiProvider::factory()->active()->create();
        $profile = AiAssistantProfile::factory()->create();

        Http::fake([
            '*/chat/completions' => Http::response([
                'choices' => [['message' => ['role' => 'assistant', 'content' => json_encode([])]]],
            ]),
        ]);

        $result = app(KnowledgeNoteExtractor::class)->extract($profile, $provider, 'متن بی‌فایده.');

        $this->assertSame([], $result['notes']);
        $this->assertNull($result['error']);
    }
}
