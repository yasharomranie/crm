<?php

namespace Tests\Feature;

use App\Domain\Ai\WhatsAppCloudApiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsAppCloudApiClientTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_sends_a_text_message_with_the_expected_payload_and_auth_header(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.1']]])]);

        $sent = app(WhatsAppCloudApiClient::class)->sendTextMessage('1234567890', 'secret-token', '989120000000', 'سلام!');

        $this->assertTrue($sent);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '1234567890/messages')
                && $request->hasHeader('Authorization', 'Bearer secret-token')
                && $request['to'] === '989120000000'
                && $request['text']['body'] === 'سلام!';
        });
    }

    public function test_a_failed_send_returns_false_without_throwing(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'invalid token']], 401)]);

        $sent = app(WhatsAppCloudApiClient::class)->sendTextMessage('1234567890', 'bad-token', '989120000000', 'سلام!');

        $this->assertFalse($sent);
    }
}
