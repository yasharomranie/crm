<?php

namespace App\Domain\Ai;

use App\Models\AiAssistantWatchedSource;

/**
 * The passive half of the assistant's web-learning capability (the active
 * half is the fetch_url tool, used mid-conversation). Re-fetches one
 * watched source and, when its content actually changed since last time,
 * drops a new AI-suggested knowledge entry for the owner to review -
 * never calls the AI provider itself, so a page changing has no effect on
 * anyone's shared chat-completion budget.
 */
class WatchedSourceChecker
{
    public function __construct(private readonly SafeUrlFetcher $fetcher) {}

    public function check(AiAssistantWatchedSource $source): void
    {
        $result = $this->fetcher->fetch($source->url);

        if (isset($result['error'])) {
            $source->forceFill(['last_checked_at' => now()])->save();

            return;
        }

        $hash = hash('sha256', $result['content']);
        $isFirstCheck = $source->last_content_hash === null;

        if (! $isFirstCheck && $hash !== $source->last_content_hash) {
            $source->profile->recordAiSuggestedKnowledge(
                "تغییر در منبع تحت نظر «{$source->displayLabel()}» ({$source->url}):\n{$result['content']}"
            );
        }

        $source->forceFill([
            'last_content_hash' => $hash,
            'last_checked_at' => now(),
        ])->save();
    }
}
