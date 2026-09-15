<?php

namespace App\Livewire;

use App\Enums\AiMessageRole;
use App\Filament\Pages\AiAssistant;
use App\Filament\Pages\Concerns\HasAiAssistantEngine;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * The panel-wide floating character: the same conversation/tool engine as
 * the full AiAssistant page (via HasAiAssistantEngine), mounted once on
 * every panel page through AdminPanelProvider's BODY_END render hook, so
 * whoever has "ai-assistant.access" can drag the mascot anywhere and open
 * a quick chat from wherever they are - not just from the dedicated page.
 *
 * mount()/render() both re-check the permission themselves: unlike the
 * dedicated page (gated by Filament's own canAccess()/HasAccessControlledPage
 * before the route is ever reached), this component is mounted
 * unconditionally by the render hook, so it must refuse to show anything
 * at all - not even an empty bubble - for a user without access.
 */
class AiAssistantWidget extends Component
{
    use HasAiAssistantEngine;

    public function mount(): void
    {
        if (! $this->userCanAccess()) {
            return;
        }

        $this->conversationId = $this->getConversations()->first()?->id;
    }

    private function userCanAccess(): bool
    {
        return (bool) Auth::user()?->can('ai-assistant.access');
    }

    public function render(): View
    {
        // Never render on the dedicated AI assistant page itself - having
        // both the full-page thread and the floating popup active over the
        // same conversation on the same screen would just be confusing
        // clutter, not a real second surface.
        if (! $this->userCanAccess() || request()->routeIs(AiAssistant::getRouteName())) {
            return view('livewire.ai-assistant-widget-hidden');
        }

        return view('livewire.ai-assistant-widget', [
            'conversations' => $this->getConversations(),
            'selected' => $this->getSelectedConversation(),
            'visibleMessages' => $this->getMessages()->filter(fn ($message) => $message->role === AiMessageRole::User
                || ($message->role === AiMessageRole::Assistant && filled($message->content))),
        ]);
    }
}
