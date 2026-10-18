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
        $recipients = User::query()->role('admin')->get();

        if (Permission::where('name', 'ai-assistant.report')->exists()) {
            $recipients = $recipients->merge(User::query()->permission('ai-assistant.report')->get())->unique('id');
        }

        $notified = 0;
        $usage = app(AssistantUsage::class);

        foreach ($recipients as $recipient) {
            // Each person only hears about modules they may see themselves.
            $messages = collect(AiAssistant::visibleAlerts($recipient))
                ->where('severity', 'danger')
                ->pluck('message')
                ->values()
                ->all();

            // Admins also hear when the monthly assistant budget runs low.
            if ($recipient->hasRole('admin') && ($costWarning = $usage->capWarning())) {
                $messages[] = $costWarning;
            }

            if ($messages === []) {
                continue;
            }

            $recipient->notify(new AssistantAlertsDigestNotification($messages));
            $notified++;
        }

        return $notified;
    }
}
