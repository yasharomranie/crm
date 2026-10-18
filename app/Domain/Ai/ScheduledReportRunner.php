<?php

namespace App\Domain\Ai;

use App\Enums\AiMessageRole;
use App\Models\AiConversation;
use App\Models\AiScheduledReport;
use App\Notifications\ScheduledReportReadyNotification;
use Illuminate\Support\Str;

/**
 * Runs one AiScheduledReport: asks the management assistant the saved
 * question as its owner, stores the answer as a conversation and pushes
 * the owner a link to it.
 */
class ScheduledReportRunner
{
    public function __construct(private readonly HeadlessAssistant $assistant) {}

    public function run(AiScheduledReport $report): ?AiConversation
    {
        $user = $report->user;

        if (! $user || ! $user->can('ai-assistant.report') || app(AssistantUsage::class)->isOverCap()) {
            $report->forceFill(['last_run_at' => now()])->save();

            return null;
        }

        // A provider failure is left to the caller: last_run_at stays put, so
        // the next scheduler pass today tries again.
        $answer = $this->assistant->answer($user, $report->prompt);

        if ($answer === null) {
            $report->forceFill(['last_run_at' => now()])->save();

            return null;
        }

        $conversation = AiConversation::create([
            'user_id' => $user->id,
            'title' => 'گزارش زمان‌بندی‌شده: '.Str::limit($report->prompt, 40),
        ]);
        $conversation->messages()->create(['role' => AiMessageRole::User, 'content' => $report->prompt]);
        $conversation->messages()->create(['role' => AiMessageRole::Assistant, 'content' => $answer]);

        $report->forceFill(['last_run_at' => now(), 'last_conversation_id' => $conversation->id])->save();

        $user->notify(new ScheduledReportReadyNotification($report));

        return $conversation;
    }
}
