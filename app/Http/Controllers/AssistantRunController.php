<?php

namespace App\Http\Controllers;

use App\Models\AiConversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Lets the assistant screens show what a running question is doing and stop
 * it. The question itself runs inside one long Livewire request, which
 * answers nothing until it ends, so these two small endpoints are polled and
 * posted to from the browser in parallel with it.
 */
class AssistantRunController extends Controller
{
    public function status(Request $request): JsonResponse
    {
        $this->authorizeAccess($request);

        $running = $this->runningConversation($request);

        return response()->json([
            'running' => $running !== null,
            'label' => $running?->run_label,
            'stopping' => (bool) $running?->cancel_requested,
        ]);
    }

    public function stop(Request $request): JsonResponse
    {
        $this->authorizeAccess($request);

        $running = $this->runningConversation($request);

        if ($running) {
            AiConversation::query()->whereKey($running->id)->toBase()->update(['cancel_requested' => true]);
        }

        return response()->json(['stopping' => $running !== null]);
    }

    private function authorizeAccess(Request $request): void
    {
        abort_unless($request->user()?->can('ai-assistant.access'), 403);
    }

    private function runningConversation(Request $request): ?AiConversation
    {
        return AiConversation::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('ai_assistant_profile_id')
            ->whereNotNull('run_label')
            ->latest('updated_at')
            ->first();
    }
}
