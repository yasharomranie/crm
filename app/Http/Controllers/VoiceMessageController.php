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
            'audio' => ['required', 'file', 'mimetypes:audio/webm,audio/ogg,audio/mp4,audio/mpeg,audio/wav,audio/x-m4a', 'max:10240'],
        ]);

        $conversation = Conversation::findOrFail($data['conversation_id']);

        abort_unless($conversation->isParticipant($request->user()), 403, 'شما عضو این گفتگو نیستید.');

        $file = $request->file('audio');

        $message = $conversation->messages()->create([
            'sender_id' => $request->user()->id,
            'body' => null,
            'file_path' => $file->store('message-attachments', config('filesystems.default')),
            'file_name' => 'پیام صوتی.'.($file->getClientOriginalExtension() ?: 'webm'),
            'mime_type' => $file->getMimeType(),
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
