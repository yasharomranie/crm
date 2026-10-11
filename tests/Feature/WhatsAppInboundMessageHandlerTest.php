<?php

namespace Tests\Feature;

use App\Domain\Ai\WhatsAppInboundMessageHandler;
use App\Models\AiAssistantProfile;
use App\Models\AiProvider;
use App\Models\WhatsAppBusinessSetting;
use App\Models\WhatsAppConversation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsAppInboundMessageHandlerTest extends TestCase
{
    use RefreshDatabase;

    private function usableSetting(?AiAssistantProfile $profile = null): WhatsAppBusinessSetting
    {
        return WhatsAppBusinessSetting::create([
            'is_enabled' => true,
            'phone_number_id' => '1234567890',
            'access_token' => 'token-abc',
            'verify_token' => 'verify-xyz',
            'ai_assistant_profile_id' => ($profile ?? AiAssistantProfile::factory()->create())->id,
        ]);
    }

    public function test_an_unusable_setting_is_ignored(): void
    {
        $setting = WhatsAppBusinessSetting::create(['is_enabled' => false]);

        app(WhatsAppInboundMessageHandler::class)->handle($setting, '989120000000', 'سلام');

        $this->assertSame(0, WhatsAppConversation::count());
    }

    public function test_a_message_creates_a_conversation_and_calls_the_assistant_with_no_tools(): void
    {
        AiProvider::factory()->active()->create();
        $profile = AiAssistantProfile::factory()->create(['description' => 'دستیار پذیرش اقامتگاه']);
        $setting = $this->usableSetting($profile);

        Http::fake([
            '*/chat/completions' => Http::response([
                'choices' => [['message' => ['role' => 'assistant', 'content' => 'سلام! چطور می‌تونم کمکتون کنم؟']]],
            ]),
            'https://graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.1']]]),
        ]);

        app(WhatsAppInboundMessageHandler::class)->handle($setting, '989120000000', 'سلام', 'رضا کریمی');

        $conversation = WhatsAppConversation::sole();
        $this->assertSame($profile->id, $conversation->ai_assistant_profile_id);
        $this->assertSame('989120000000', $conversation->wa_id);
        $this->assertSame('رضا کریمی', $conversation->contact_name);

        $messages = $conversation->messages;
        $this->assertSame('inbound', $messages[0]->direction);
        $this->assertSame('سلام', $messages[0]->content);
        $this->assertSame('outbound', $messages[1]->direction);
        $this->assertSame('سلام! چطور می‌تونم کمکتون کنم؟', $messages[1]->content);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'chat/completions') && ! array_key_exists('tools', $request->data()));
        Http::assertSent(fn ($request) => str_contains($request->url(), 'graph.facebook.com') && $request['to'] === '989120000000');
    }

    public function test_a_second_message_reuses_the_same_conversation_and_sends_history(): void
    {
        AiProvider::factory()->active()->create();
        $setting = $this->usableSetting();

        Http::fake([
            '*/chat/completions' => Http::response(['choices' => [['message' => ['role' => 'assistant', 'content' => 'پاسخ اول']]]]),
            'https://graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.1']]]),
        ]);

        $handler = app(WhatsAppInboundMessageHandler::class);
        $handler->handle($setting, '989120000000', 'سؤال اول');
        $handler->handle($setting, '989120000000', 'سؤال دوم');

        $this->assertSame(1, WhatsAppConversation::count());
        $this->assertSame(4, WhatsAppConversation::sole()->messages()->count());
    }

    public function test_a_provider_error_leaves_no_outbound_message_and_sends_nothing(): void
    {
        AiProvider::factory()->active()->create();
        $setting = $this->usableSetting();

        Http::fake([
            '*/chat/completions' => Http::response(['error' => ['message' => 'quota exceeded']], 429),
        ]);

        app(WhatsAppInboundMessageHandler::class)->handle($setting, '989120000000', 'سلام');

        $conversation = WhatsAppConversation::sole();
        $this->assertSame(1, $conversation->messages()->count());
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'graph.facebook.com'));
    }

    public function test_no_active_provider_still_records_the_inbound_message(): void
    {
        $setting = $this->usableSetting();

        app(WhatsAppInboundMessageHandler::class)->handle($setting, '989120000000', 'سلام');

        $conversation = WhatsAppConversation::sole();
        $this->assertSame(1, $conversation->messages()->count());
    }
}
