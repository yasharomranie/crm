<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Models\User;
use App\Notifications\NewMessageReceived;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewMessageReceivedTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_web_push_body_falls_back_to_a_label_for_a_voice_note_with_no_text(): void
    {
        $message = Message::factory()->create([
            'body' => null,
            'file_path' => 'voice-notes/test.webm',
            'file_name' => 'test.webm',
            'mime_type' => 'audio/webm',
        ]);

        $push = (new NewMessageReceived($message))->toWebPush(User::factory()->create(), new NewMessageReceived($message));

        $this->assertSame('پیام صوتی', $push->toArray()['body']);
    }

    public function test_the_web_push_body_falls_back_to_the_file_name_for_a_file_attachment_with_no_text(): void
    {
        $message = Message::factory()->create([
            'body' => null,
            'file_path' => 'attachments/report.pdf',
            'file_name' => 'report.pdf',
            'mime_type' => 'application/pdf',
        ]);

        $push = (new NewMessageReceived($message))->toWebPush(User::factory()->create(), new NewMessageReceived($message));

        $this->assertSame('report.pdf', $push->toArray()['body']);
    }

    public function test_the_web_push_body_uses_the_message_text_when_there_is_no_attachment(): void
    {
        $message = Message::factory()->create(['body' => 'سلام، حالت چطوره؟']);

        $push = (new NewMessageReceived($message))->toWebPush(User::factory()->create(), new NewMessageReceived($message));

        $this->assertSame('سلام، حالت چطوره؟', $push->toArray()['body']);
    }
}
