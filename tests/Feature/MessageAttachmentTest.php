<?php

namespace Tests\Feature;

use App\Filament\Pages\Messenger;
use App\Models\Conversation;
use App\Models\User;
use App\Notifications\NewMessageReceived;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class MessageAttachmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_sending_a_message_with_an_image_attachment_stores_it_and_needs_no_caption(): void
    {
        Storage::fake();
        Notification::fake();

        $sender = User::factory()->create();
        $recipient = User::factory()->create();
        $conversation = Conversation::findOrCreateDirect($sender, $recipient);

        $this->actingAs($sender);

        Livewire::test(Messenger::class)
            ->set('conversationId', $conversation->id)
            ->set('body', '')
            ->set('attachment', UploadedFile::fake()->image('photo.jpg'))
            ->call('sendMessage')
            ->assertHasNoErrors();

        $message = $conversation->messages()->latest()->firstOrFail();

        $this->assertNull($message->body);
        $this->assertNotNull($message->file_path);
        $this->assertSame('photo.jpg', $message->file_name);
        $this->assertSame('image/jpeg', $message->mime_type);
        $this->assertTrue($message->attachmentIsImage());
        Storage::disk()->assertExists($message->file_path);
    }

    public function test_only_a_conversation_participant_can_download_a_message_attachment(): void
    {
        Storage::fake();

        $sender = User::factory()->create();
        $recipient = User::factory()->create();
        $outsider = User::factory()->create();
        $conversation = Conversation::findOrCreateDirect($sender, $recipient);

        $message = $conversation->messages()->create([
            'sender_id' => $sender->id,
            'body' => null,
            'file_path' => 'message-attachments/test.jpg',
            'file_name' => 'test.jpg',
            'mime_type' => 'image/jpeg',
            'size_kb' => 12,
        ]);
        Storage::disk()->put($message->file_path, 'fake image bytes');

        $this->actingAs($recipient)
            ->get(route('messages.attachment', $message))
            ->assertSuccessful();

        $this->actingAs($outsider)
            ->get(route('messages.attachment', $message))
            ->assertForbidden();
    }

    public function test_uploading_a_voice_note_creates_a_message_and_notifies_the_other_participant(): void
    {
        Storage::fake();
        Notification::fake();

        $sender = User::factory()->create();
        $recipient = User::factory()->create();
        $conversation = Conversation::findOrCreateDirect($sender, $recipient);

        $this->actingAs($sender)
            ->post(route('messages.voice-note.store'), [
                'conversation_id' => $conversation->id,
                'audio' => UploadedFile::fake()->create('voice-note.webm', 50, 'audio/webm'),
            ])
            ->assertSuccessful()
            ->assertJson(['status' => 'sent']);

        $message = $conversation->messages()->latest()->firstOrFail();

        $this->assertNull($message->body);
        $this->assertTrue($message->attachmentIsAudio());
        Notification::assertSentTo($recipient, NewMessageReceived::class);
    }

    /**
     * A real MediaRecorder audio-only WebM/Opus clip (not a fake with a
     * declared-but-unverified mime type) - PHP's real fileinfo sniffing of
     * this exact file reports video/webm, not audio/webm, because the
     * Matroska container's magic bytes don't distinguish an audio-only
     * track from a video one. This is what actually broke voice notes:
     * every real recording failed the mimetypes validation rule, and the
     * one that passed validation (an audio-only file with a video/webm
     * mime type slipping through the widened rule) would have rendered as
     * a generic download instead of a player without the normalization in
     * VoiceMessageController.
     */
    public function test_uploading_a_real_webm_recording_is_accepted_and_stored_as_audio(): void
    {
        Storage::fake();
        Notification::fake();

        $sender = User::factory()->create();
        $recipient = User::factory()->create();
        $conversation = Conversation::findOrCreateDirect($sender, $recipient);

        $realWebmBytes = file_get_contents(__DIR__.'/../Fixtures/sample-voice-note.webm');
        $file = UploadedFile::fake()->createWithContent('voice-note.webm', $realWebmBytes);

        $this->assertSame('video/webm', $file->getMimeType(), 'sanity check: this fixture must sniff as video/webm for the test to be meaningful');

        $this->actingAs($sender)
            ->post(route('messages.voice-note.store'), [
                'conversation_id' => $conversation->id,
                'audio' => $file,
            ])
            ->assertSuccessful()
            ->assertJson(['status' => 'sent']);

        $message = $conversation->messages()->latest()->firstOrFail();

        $this->assertSame('audio/webm', $message->mime_type);
        $this->assertTrue($message->attachmentIsAudio());
    }

    public function test_a_non_participant_cannot_upload_a_voice_note_into_a_conversation(): void
    {
        Storage::fake();

        $sender = User::factory()->create();
        $recipient = User::factory()->create();
        $outsider = User::factory()->create();
        $conversation = Conversation::findOrCreateDirect($sender, $recipient);

        $this->actingAs($outsider)
            ->post(route('messages.voice-note.store'), [
                'conversation_id' => $conversation->id,
                'audio' => UploadedFile::fake()->create('voice-note.webm', 50, 'audio/webm'),
            ])
            ->assertForbidden();
    }
}
