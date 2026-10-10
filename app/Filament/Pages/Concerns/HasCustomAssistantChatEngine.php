<?php

namespace App\Filament\Pages\Concerns;

use App\Enums\AiMessageRole;
use App\Exceptions\AiProviderException;
use App\Models\AiAssistantProfile;
use App\Models\AiConversation;
use App\Models\AiProvider;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * A small, tools-free sibling of HasAiAssistantEngine, for the custom
 * assistants created in the AI Assistants Hub (app/Filament/Pages/
 * AiAssistantsHub.php). It reuses the exact same AiConversation/AiMessage
 * models and AiProvider::chatCompletion() call, but never calls the
 * ~50-tool agent loop that trait runs - a custom profile's only
 * "capability" in this phase is the system prompt the user wrote for it.
 */
trait HasCustomAssistantChatEngine
{
    public ?int $activeProfileId = null;

    public ?int $conversationId = null;

    public string $body = '';

    public bool $isThinking = false;

    public function activeProfile(): ?AiAssistantProfile
    {
        if (! $this->activeProfileId) {
            return null;
        }

        return AiAssistantProfile::query()
            ->visibleTo(Auth::user())
            ->find($this->activeProfileId);
    }

    /**
     * @return Collection<int, AiConversation>
     */
    public function getProfileConversations(): Collection
    {
        if (! $this->activeProfileId) {
            return new Collection;
        }

        return AiConversation::query()
            ->where('user_id', Auth::id())
            ->where('ai_assistant_profile_id', $this->activeProfileId)
            ->latest('updated_at')
            ->get();
    }

    public function getSelectedProfileConversation(): ?AiConversation
    {
        if (! $this->conversationId) {
            return null;
        }

        return AiConversation::query()
            ->where('user_id', Auth::id())
            ->where('ai_assistant_profile_id', $this->activeProfileId)
            ->find($this->conversationId);
    }

    public function getProfileMessages(): Collection
    {
        return $this->getSelectedProfileConversation()?->messages()->get() ?? new Collection;
    }

    public function openProfile(int $profileId): void
    {
        $profile = AiAssistantProfile::query()->visibleTo(Auth::user())->find($profileId);

        if (! $profile) {
            return;
        }

        $this->activeProfileId = $profile->id;
        $this->conversationId = $this->getProfileConversations()->first()?->id;
        $this->body = '';
    }

    public function closeProfile(): void
    {
        $this->activeProfileId = null;
        $this->conversationId = null;
        $this->body = '';
    }

    public function newProfileConversation(): void
    {
        $conversation = AiConversation::create([
            'user_id' => Auth::id(),
            'ai_assistant_profile_id' => $this->activeProfileId,
        ]);

        $this->conversationId = $conversation->id;
        $this->body = '';
    }

    public function selectProfileConversation(int $conversationId): void
    {
        $this->conversationId = $conversationId;
        $this->body = '';
    }

    public function sendToProfile(): void
    {
        $profile = $this->activeProfile();
        $body = trim($this->body);

        if ($body === '' || ! $profile) {
            return;
        }

        $provider = AiProvider::active();

        if (! $provider) {
            Notification::make()->title(AiProviderException::noActiveProvider()->getMessage())->warning()->send();

            return;
        }

        $conversation = $this->getSelectedProfileConversation() ?? AiConversation::create([
            'user_id' => Auth::id(),
            'ai_assistant_profile_id' => $profile->id,
        ]);
        $this->conversationId = $conversation->id;

        $conversation->messages()->create(['role' => AiMessageRole::User, 'content' => $body]);
        $this->body = '';

        if (! $conversation->title) {
            $conversation->update(['title' => $conversation->deriveTitle()]);
        }

        $this->isThinking = true;

        try {
            $messages = [
                ['role' => 'system', 'content' => $profile->description ?: 'شما یک دستیار هوشمند کمکی هستید.'],
                ...$conversation->messages()->get()->map(fn ($message) => $message->toChatPayload())->all(),
            ];

            $assistantMessage = $provider->chatCompletion($messages);

            $conversation->messages()->create([
                'role' => AiMessageRole::Assistant,
                'content' => $assistantMessage['content'] ?? null,
            ]);
        } catch (AiProviderException $exception) {
            Notification::make()->title($exception->getMessage())->danger()->send();
        } finally {
            $this->isThinking = false;
        }

        $this->dispatch('message-sent');
    }

    /**
     * Copies the active conversation's visible messages onto a brand-new
     * conversation under a different profile the user can see - the
     * "manual forward" pattern (never automatic cross-assistant
     * delegation): the target assistant starts a fresh thread that
     * already contains this context, and the user decides what (if
     * anything) to ask it next.
     */
    public function forwardConversationTo(int $targetProfileId): void
    {
        $source = $this->getSelectedProfileConversation();
        $target = AiAssistantProfile::query()->visibleTo(Auth::user())->find($targetProfileId);

        if (! $source || ! $target) {
            return;
        }

        $forwarded = AiConversation::create([
            'user_id' => Auth::id(),
            'ai_assistant_profile_id' => $target->id,
            'title' => 'فوروارد شده از «'.($this->activeProfile()?->displayName() ?? 'دستیار دیگر').'»',
        ]);

        foreach ($source->messages()->get() as $message) {
            if (! in_array($message->role, [AiMessageRole::User, AiMessageRole::Assistant], true) || blank($message->content)) {
                continue;
            }

            $forwarded->messages()->create([
                'role' => $message->role,
                'content' => $message->content,
            ]);
        }

        $this->activeProfileId = $target->id;
        $this->conversationId = $forwarded->id;
        $this->body = '';

        Notification::make()->title('گفتگو برای «'.$target->displayName().'» فوروارد شد.')->success()->send();
    }
}
