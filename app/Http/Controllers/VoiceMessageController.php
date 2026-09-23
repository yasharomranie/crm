<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\User;
use App\Notifications\NewMessageReceived;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Backs the "ارسال پیام صوتی" (voice note) recorder in messenger.blade.php.
 * A real recorded audio clip needs its own endpoint rather than going
 * through Messenger::sendMessage() - the browser's MediaRecorder API
 * produces a raw Blob, not a value a normal <input type="file"> (and so
 * Livewire's upload flow) ever sees, so it's sent here as plain
 * multipart/form-data instead.
 */
class VoiceMessageController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'conversation_id' => ['required', 'integer'],
            // video/webm is deliberately included: an audio-only WebM/Opus
            // recording from MediaRecorder (what the browser actually sends
            // here) is a libmagic/fileinfo quirk away from audio/webm - the
            // Matroska container's magic bytes don't distinguish an
            // audio-only track from a video one, so PHP's real-content mime
            // sniffing reports video/webm for it even though it's audio.
            'audio' => ['required', 'file', 'mimetypes:audio/webm,video/webm,audio/ogg,audio/mp4,audio/mpeg,audio/wav,audio/x-m4a', 'max:10240'],
        ]);

        $conversation = Conversation::findOrFail($data['conversation_id']);

        abort_unless($conversation->isParticipant($request->user()), 403, 'شما عضو این گفتگو نیستید.');

        $file = $request->file('audio');

        // This endpoint only ever handles audio, but libmagic/fileinfo
        // sniffs an audio-only WebM/Opus recording (what MediaRecorder
        // actually produces in Chrome/Firefox) as video/webm - the
        // Matroska container's magic bytes don't distinguish an audio-only
        // track from a video one. Normalize it back so
        // Message::attachmentIsAudio() renders it as a player instead of a
        // generic file-download row.
        $mimeType = $file->getMimeType() === 'video/webm' ? 'audio/webm' : $file->getMimeType();

        $message = $conversation->messages()->create([
            'sender_id' => $request->user()->id,
            'body' => null,
            'file_path' => $file->store('message-attachments', config('filesystems.default')),
            'file_name' => 'پیام صوتی.'.($file->getClientOriginalExtension() ?: 'webm'),
            'mime_type' => $mimeType,
            'size_kb' => (int) round($file->getSize() / 1024),
        ]);

        $conversation->markReadFor($request->user());

        $conversation->participants()
            ->where('user_id', '!=', $request->user()->id)
            ->with('user')
            ->get()
            ->pluck('user')
            ->filter()
            ->each(fn (User $participant) => $participant->notify(new NewMessageReceived($message)));

        return response()->json(['status' => 'sent', 'message_id' => $message->id]);
    }
}
