<?php

namespace Tests\Feature;

use App\Jobs\ProcessWhatsAppInboundMessageJob;
use App\Models\AiAssistantProfile;
use App\Models\WhatsAppBusinessSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class WhatsAppWebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    private function usableSetting(): WhatsAppBusinessSetting
    {
        return WhatsAppBusinessSetting::create([
            'is_enabled' => true,
            'phone_number_id' => '1234567890',
            'access_token' => 'token-abc',
            'verify_token' => 'my-verify-token',
            'ai_assistant_profile_id' => AiAssistantProfile::factory()->create()->id,
        ]);
    }

    public function test_the_verification_handshake_echoes_the_challenge_when_the_token_matches(): void
    {
        $this->usableSetting();

        $response = $this->get('/api/v1/whatsapp/webhook?hub.mode=subscribe&hub.verify_token=my-verify-token&hub.challenge=12345');

        $response->assertOk();
        $response->assertSee('12345');
    }

    public function test_the_verification_handshake_is_rejected_with_the_wrong_token(): void
    {
        $this->usableSetting();

        $response = $this->get('/api/v1/whatsapp/webhook?hub.mode=subscribe&hub.verify_token=wrong&hub.challenge=12345');

        $response->assertForbidden();
    }

    public function test_the_verification_handshake_is_rejected_when_not_usable(): void
    {
        WhatsAppBusinessSetting::create(['is_enabled' => false]);

        $response = $this->get('/api/v1/whatsapp/webhook?hub.mode=subscribe&hub.verify_token=anything&hub.challenge=12345');

        $response->assertForbidden();
    }

    public function test_an_incoming_message_dispatches_the_processing_job(): void
    {
        $this->usableSetting();
        Bus::fake();

        $payload = [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'id' => '1',
                'changes' => [[
                    'value' => [
                        'contacts' => [['profile' => ['name' => 'رضا کریمی'], 'wa_id' => '989120000000']],
                        'messages' => [['from' => '989120000000', 'id' => 'wamid.1', 'text' => ['body' => 'سلام']]],
                    ],
                    'field' => 'messages',
                ]],
            ]],
        ];

        $response = $this->postJson('/api/v1/whatsapp/webhook', $payload);

        $response->assertOk();
        Bus::assertDispatched(ProcessWhatsAppInboundMessageJob::class, fn (ProcessWhatsAppInboundMessageJob $job) => $job->waId === '989120000000'
            && $job->text === 'سلام'
            && $job->contactName === 'رضا کریمی');
    }

    public function test_a_status_update_payload_without_a_message_dispatches_nothing(): void
    {
        $this->usableSetting();
        Bus::fake();

        $payload = [
            'entry' => [[
                'changes' => [[
                    'value' => ['statuses' => [['id' => 'wamid.1', 'status' => 'delivered']]],
                    'field' => 'messages',
                ]],
            ]],
        ];

        $response = $this->postJson('/api/v1/whatsapp/webhook', $payload);

        $response->assertOk();
        Bus::assertNotDispatched(ProcessWhatsAppInboundMessageJob::class);
    }

    public function test_an_incoming_message_is_ignored_when_the_setting_is_not_usable(): void
    {
        WhatsAppBusinessSetting::create(['is_enabled' => false]);
        Bus::fake();

        $payload = [
            'entry' => [[
                'changes' => [[
                    'value' => ['messages' => [['from' => '989120000000', 'text' => ['body' => 'سلام']]]],
                ]],
            ]],
        ];

        $response = $this->postJson('/api/v1/whatsapp/webhook', $payload);

        $response->assertOk();
        Bus::assertNotDispatched(ProcessWhatsAppInboundMessageJob::class);
    }
}
