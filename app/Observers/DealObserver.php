<?php

namespace App\Observers;

use App\Domain\Webhooks\WebhookDispatcher;
use App\Enums\WebhookEventType;
use App\Models\Deal;

/**
 * Fires on every stage change regardless of source - the sales Kanban's
 * drag-and-drop (SalesPipeline::moveStage()) and any other caller all go
 * through $deal->update(), which fires this the same way.
 */
class DealObserver
{
    public function __construct(private WebhookDispatcher $webhooks) {}

    public function updated(Deal $deal): void
    {
        if (! $deal->wasChanged('stage')) {
            return;
        }

        $this->webhooks->dispatch($deal->external_api_client_id, WebhookEventType::DealStageChanged, [
            'deal_id' => $deal->id,
            'title' => $deal->title,
            'stage' => $deal->stage->value,
            'previous_stage' => $deal->getOriginal('stage')?->value,
        ]);
    }
}
