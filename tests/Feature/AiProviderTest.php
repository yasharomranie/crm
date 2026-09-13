<?php

namespace Tests\Feature;

use App\Exceptions\AiProviderException;
use App\Models\AiProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiProviderTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_api_key_is_encrypted_at_rest(): void
    {
        $provider = AiProvider::factory()->create(['api_key' => 'sk-super-secret']);

        $rawValue = DB::table('ai_providers')->where('id', $provider->id)->value('api_key');

        $this->assertNotSame('sk-super-secret', $rawValue);
        $this->assertSame('sk-super-secret', $provider->fresh()->api_key);
    }

    public function test_only_one_provider_can_be_active_at_a_time(): void
    {
        $first = AiProvider::factory()->active()->create();
        $second = AiProvider::factory()->active()->create();

        $this->assertFalse($first->fresh()->is_active);
        $this->assertTrue($second->fresh()->is_active);
        $this->assertTrue(AiProvider::active()->is($second));
    }

    public function test_chat_completion_returns_the_assistant_message(): void
    {
        Http::fake([
            '*/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['role' => 'assistant', 'content' => 'سلام!']],
                ],
            ]),
        ]);

        $provider = AiProvider::factory()->create();

        $message = $provider->chatCompletion([['role' => 'user', 'content' => 'سلام']]);

        $this->assertSame('سلام!', $message['content']);
    }

    public function test_chat_completion_throws_on_a_failed_request(): void
    {
        Http::fake([
            '*/chat/completions' => Http::response(['error' => ['message' => 'Invalid API key']], 401),
        ]);

        $provider = AiProvider::factory()->create();

        $this->expectException(AiProviderException::class);
        $this->expectExceptionMessage('Invalid API key');

        $provider->chatCompletion([['role' => 'user', 'content' => 'سلام']]);
    }

    public function test_list_models_returns_model_ids(): void
    {
        Http::fake([
            '*/models' => Http::response([
                'data' => [
                    ['id' => 'gpt-4o-mini'],
                    ['id' => 'gpt-4o'],
                ],
            ]),
        ]);

        $provider = AiProvider::factory()->create();

        $this->assertSame(['gpt-4o', 'gpt-4o-mini'], $provider->listModels());
    }
}
