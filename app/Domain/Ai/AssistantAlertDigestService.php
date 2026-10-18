<?php

namespace App\Domain\Ai;

use App\Filament\Pages\AiAssistant;
use App\Models\User;
use App\Notifications\AssistantAlertsDigestNotification;
use Spatie\Permission\Models\Permission;

/**
 * Once a morning, pushes the people who may use the management assistant the
 * critical ("danger") alerts it already computes for its own page, so they
 * hear about an over-budget project or a breached support SLA without
 * having to open it. Nothing is sent on a quiet day.
 */
class AssistantAlertDigestService
{
    /**
     * @return int how many people were notified
     */
    public function notifyDailyDigest(): int
    {
        $messages = collect(AiAssistant::computeAlerts())
            ->where('severity', 'danger')
            ->pluck('message')
            ->values()
            ->all();

        if ($messages === []) {
            return 0;
        }

        $recipients = User::query()->role('admin')->get();

        if (Permission::where('name', 'ai-assistant.report')->exists()) {
            $recipients = $recipients->merge(User::query()->permission('ai-assistant.report')->get())->unique('id');
        }

        foreach ($recipients as $recipient) {
            $recipient->notify(new AssistantAlertsDigestNotification($messages));
        }

        return $recipients->count();
    }
}
