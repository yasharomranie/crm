<?php

namespace App\Filament\Pages;

use App\Enums\AiMessageRole;
use App\Filament\Pages\Concerns\HasAccessControlledPage;
use App\Models\AiAssistantProfile;
use App\Models\AiMessage;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

/**
 * A per-assistant usage overview for the AI Assistants Hub - how much
 * each profile is actually used (messages, consultations given/received,
 * 👍/👎 feedback) and what passive/automatic machinery it has running
 * (watched sources, daily scheduled prompts). Exists because, as the
 * number of custom assistants and the AI spend behind them (chat
 * messages, consultations, scheduled runs) grows, the owner otherwise has
 * no single place to see which assistants are actually earning their
 * keep versus sitting unused.
 */
class AiAssistantUsageDashboard extends Page
{
    use HasAccessControlledPage;

    protected string $view = 'filament.pages.ai-assistant-usage-dashboard';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|\UnitEnum|null $navigationGroup = 'دستیار هوشمند مدیریتی';

    protected static ?string $navigationLabel = 'آمار مصرف دستیاران';

    protected static ?string $title = 'آمار مصرف دستیاران هوشمند';

    public static function accessControlPermission(): string
    {
        return 'ai-assistant-usage-dashboard.access';
    }

    /**
     * The management assistant itself (the full page and the floating
     * widget), for the last 30 days across all users: how much it is used,
     * what it cost in tokens, how people rated it and which tools it calls.
     *
     * @return array{questions: int, conversations: int, prompt_tokens: int, completion_tokens: int, feedback_up: int, feedback_down: int, top_tools: array<int, array{tool: string, calls: int}>}
     */
    public function getMainAssistantStats(): array
    {
        $since = now()->subDays(30);

        $messages = AiMessage::query()
            ->where('ai_messages.created_at', '>=', $since)
            ->whereHas('conversation', fn ($query) => $query->whereNull('ai_assistant_profile_id'));

        $topTools = Activity::query()
            ->where('log_name', 'ai_assistant')
            ->where('created_at', '>=', $since)
            ->get()
            ->countBy(fn (Activity $activity) => (string) ($activity->properties['tool'] ?? ''))
            ->sortDesc()
            ->take(5)
            ->map(fn (int $calls, string $tool) => ['tool' => $tool, 'calls' => $calls])
            ->values()
            ->all();

        return [
            'questions' => (clone $messages)->where('role', AiMessageRole::User)->count(),
            'conversations' => DB::table('ai_conversations')->whereNull('ai_assistant_profile_id')->where('created_at', '>=', $since)->count(),
            'prompt_tokens' => (int) (clone $messages)->sum('prompt_tokens'),
            'completion_tokens' => (int) (clone $messages)->sum('completion_tokens'),
            'feedback_up' => (clone $messages)->where('feedback', 'up')->count(),
            'feedback_down' => (clone $messages)->where('feedback', 'down')->count(),
            'top_tools' => $topTools,
        ];
    }

    /**
     * @return array<int, array{profile: AiAssistantProfile, message_count: int, conversation_count: int, consultations_asked: int, consultations_answered: int, feedback_up: int, feedback_down: int, watched_sources_count: int, active_scheduled_prompts_count: int}>
     */
    public function getProfileUsageStats(): array
    {
        $profiles = AiAssistantProfile::query()
            ->visibleTo(Auth::user())
            ->withCount([
                'conversations',
                'watchedSources',
                'scheduledPrompts as active_scheduled_prompts_count' => fn ($query) => $query->where('is_active', true),
                'consultationsAsRequester',
                'consultationsAsConsultant',
            ])
            ->with(['conversations.messages'])
            ->get();

        return $profiles->map(function (AiAssistantProfile $profile) {
            $messages = $profile->conversations->flatMap->messages;

            return [
                'profile' => $profile,
                'message_count' => $messages->whereIn('role', [AiMessageRole::User, AiMessageRole::Assistant])->count(),
                'conversation_count' => $profile->conversations_count,
                'consultations_asked' => $profile->consultations_as_requester_count,
                'consultations_answered' => $profile->consultations_as_consultant_count,
                'feedback_up' => $messages->where('feedback', 'up')->count(),
                'feedback_down' => $messages->where('feedback', 'down')->count(),
                'watched_sources_count' => $profile->watched_sources_count,
                'active_scheduled_prompts_count' => $profile->active_scheduled_prompts_count,
            ];
        })->sortByDesc('message_count')->values()->all();
    }
}
