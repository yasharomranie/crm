<?php

namespace Tests\Feature;

use App\Domain\Ai\WhatsAppInboundMessageHandler;
use App\Jobs\ProcessWhatsAppInboundMessageJob;
use App\Models\AiAssistantProfile;
use App\Models\AiProvider;
use App\Models\WhatsAppBusinessSetting;
use App\Models\WhatsAppConversation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ProcessWhatsAppInboundMessageJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_processes_the_message_against_the_current_setting(): void
    {
        AiProvider::factory()->active()->create();
        WhatsAppBusinessSetting::create([
            'is_enabled' => true,
            'phone_number_id' => '1234567890',
            'access_token' => 'token-abc',
            'verify_token' => 'verify-xyz',
            'ai_assistant_profile_id' => AiAssistantProfile::factory()->create()->id,
        ]);

        Http::fake([
            '*/chat/completions' => Http::response(['choices' => [['message' => ['role' => 'assistant', 'content' => 'پاسخ']]]]),
            'https://graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.1']]]),
        ]);

        (new ProcessWhatsAppInboundMessageJob('989120000000', 'سلام', 'رضا'))->handle(app(WhatsAppInboundMessageHandler::class));

        $this->assertSame(1, WhatsAppConversation::count());
    }

    public function test_an_unexpected_failure_is_logged_and_not_thrown(): void
    {
        Log::spy();
        WhatsAppBusinessSetting::create(['is_enabled' => false]);

        $this->mock(WhatsAppInboundMessageHandler::class, function ($mock) {
            $mock->shouldReceive('handle')->andThrow(new \RuntimeException('خطای غیرمنتظره'));
        });

        (new ProcessWhatsAppInboundMessageJob('989120000000', 'سلام'))->handle(app(WhatsAppInboundMessageHandler::class));

        Log::shouldHaveReceived('warning')->once();
    }
}
