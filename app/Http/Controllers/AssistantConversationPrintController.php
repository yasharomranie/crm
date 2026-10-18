<?php

namespace App\Http\Controllers;

use App\Enums\AiMessageRole;
use App\Models\AiConversation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * A print-friendly page of one of the user's own assistant conversations;
 * "Save as PDF" in the browser's print dialog turns it into a PDF.
 */
class AssistantConversationPrintController extends Controller
{
    public function show(Request $request, AiConversation $conversation): View
    {
        abort_unless($request->user()?->can('ai-assistant.access'), 403);
        abort_unless($conversation->user_id === $request->user()->id && $conversation->ai_assistant_profile_id === null, 404);

        $messages = $conversation->messages()
            ->whereIn('role', [AiMessageRole::User->value, AiMessageRole::Assistant->value])
            ->get()
            ->filter(fn ($message) => filled($message->content));

        return view('ai-assistant.conversation-print', [
            'conversation' => $conversation,
            'messages' => $messages,
            'auto' => $request->boolean('auto'),
        ]);
    }
}
