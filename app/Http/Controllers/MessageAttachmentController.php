<?php

namespace App\Http\Controllers;

use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams a message's file/voice-note attachment from the app's private
 * default disk. inline (not ->download()) so an <img> or <audio> tag in
 * the chat bubble can embed it directly - the browser still lets the
 * viewer save it via its own UI for non-previewable types.
 *
 * Storage::disk() with no argument, never Storage::disk('local') by name -
 * see FormSubmissionFileController for why.
 */
class MessageAttachmentController extends Controller
{
    public function show(Request $request, Message $message): StreamedResponse
    {
        abort_unless($message->conversation->isParticipant($request->user()), 403, 'شما به این پیام دسترسی ندارید.');

        abort_unless($message->file_path, 404);

        return Storage::disk()->response($message->file_path, $message->file_name);
    }
}
