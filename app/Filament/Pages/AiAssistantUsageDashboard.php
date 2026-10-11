<?php

namespace App\Filament\Pages;

use App\Enums\AiMessageRole;
use App\Filament\Pages\Concerns\HasAccessControlledPage;
use App\Models\AiAssistantProfile;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

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
