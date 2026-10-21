<?php

namespace App\Domain\Ai;

use App\Enums\AiMessageRole;
use App\Models\AiAssistantConsultation;
use App\Models\AiAssistantProfile;
use App\Models\AiMessage;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Activity;

/**
 * The data behind the "team map": who the assistants are (nodes) and how
 * they have worked together (edges) - consultations between profiles, chats
 * handed over, and the management assistant asking a specialist.
 *
 * Everything comes from rows that already exist, scoped to the assistants
 * the user may see, so the map shows no one else's private assistant.
 */
class AiTeamMapData
{
    public const MANAGER_ID = 'manager';

    public const WINDOW_DAYS = 30;

    /** An exchange this recent still "glows" on the map. */
    public const LIVE_SECONDS = 300;

    private const COLORS = [
        'Amber' => '#fbbf24', 'Orange' => '#fb923c', 'Red' => '#f87171', 'Pink' => '#f472b6', 'Purple' => '#c084fc',
        'Indigo' => '#818cf8', 'Blue' => '#60a5fa', 'Teal' => '#2dd4bf', 'Emerald' => '#34d399', 'Green' => '#4ade80',
    ];

    /**
     * @return array{nodes: list<array<string, mixed>>, edges: list<array<string, mixed>>, feed: list<array<string, mixed>>, totals: array<string, int>}
     */
    public function build(User $user): array
    {
        $since = now()->subDays(self::WINDOW_DAYS);
        $profiles = AiAssistantProfile::query()->visibleTo($user)->get()->keyBy('id');
        $exchanges = $this->exchanges($user, $profiles, $since);

        $edges = $exchanges
            ->groupBy(fn (array $exchange) => $exchange['from'].'>'.$exchange['to'].'>'.$exchange['kind'])
            ->map(function (Collection $group) {
                $last = $group->max('at');

                return [
                    'id' => $group->first()['from'].'>'.$group->first()['to'].'>'.$group->first()['kind'],
                    'source' => (string) $group->first()['from'],
                    'target' => (string) $group->first()['to'],
                    'kind' => $group->first()['kind'],
                    'count' => $group->count(),
                    'live' => $last->gt(now()->subSeconds(self::LIVE_SECONDS)),
                ];
            })
            ->values()
            ->all();

        $messageCounts = AiMessage::query()
            ->where('created_at', '>=', $since)
            ->where('role', AiMessageRole::Assistant->value)
            ->whereNotNull('ai_assistant_profile_id')
            ->selectRaw('ai_assistant_profile_id, count(*) as total, max(created_at) as last_at')
            ->groupBy('ai_assistant_profile_id')
            ->get()
            ->keyBy('ai_assistant_profile_id');

        $given = $exchanges->where('kind', 'consult')->countBy('to');
        $asked = $exchanges->where('kind', 'consult')->countBy('from');
        $liveIds = collect($edges)->where('live', true)->flatMap(fn (array $edge) => [$edge['source'], $edge['target']])->unique();

        $nodes = $profiles->map(fn (AiAssistantProfile $profile) => [
            'id' => (string) $profile->id,
            'label' => $profile->displayName(),
            'color' => self::COLORS[$profile->avatar_color] ?? self::COLORS['Amber'],
            'shape' => $profile->avatar_shape,
            'consultable' => (bool) $profile->consultable,
            'specialties' => collect($profile->specialties ?? [])->take(4)->values()->all(),
            'messages' => (int) ($messageCounts[$profile->id]->total ?? 0),
            'consulted' => (int) ($given[$profile->id] ?? 0),
            'asked' => (int) ($asked[$profile->id] ?? 0),
            'live' => $liveIds->contains((string) $profile->id),
        ])->values()->prepend([
            'id' => self::MANAGER_ID,
            'label' => 'دستیار مدیریتی',
            'color' => '#e2e8f0',
            'shape' => 'manager',
            'consultable' => false,
            'specialties' => [],
            'messages' => 0,
            'consulted' => 0,
            'asked' => $exchanges->where('from', self::MANAGER_ID)->count(),
            'live' => $liveIds->contains(self::MANAGER_ID),
        ])->all();

        return [
            'nodes' => $nodes,
            'edges' => $edges,
            'feed' => $this->feed($exchanges, $profiles),
            'totals' => [
                'assistants' => $profiles->count(),
                'consultations' => $exchanges->where('kind', 'consult')->count(),
                'handoffs' => $exchanges->where('kind', 'handoff')->count(),
                'live' => count($liveIds),
            ],
        ];
    }

    /**
     * @param  Collection<int, AiAssistantProfile>  $profiles  keyed by id
     * @return Collection<int, array{from: string, to: string, kind: string, at: CarbonInterface}>
     */
    private function exchanges(User $user, Collection $profiles, CarbonInterface $since): Collection
    {
        $visible = fn (mixed $id) => $profiles->has((int) $id);

        $consultations = AiAssistantConsultation::query()
            ->where('created_at', '>=', $since)
            ->latest()
            ->limit(500)
            ->get()
            ->filter(fn (AiAssistantConsultation $consultation) => $visible($consultation->consulting_ai_assistant_profile_id) && $visible($consultation->consulted_ai_assistant_profile_id))
            ->map(fn (AiAssistantConsultation $consultation) => [
                'from' => (string) $consultation->consulting_ai_assistant_profile_id,
                'to' => (string) $consultation->consulted_ai_assistant_profile_id,
                'kind' => 'consult',
                'at' => $consultation->created_at,
            ]);

        $fromManager = Activity::query()
            ->where('log_name', 'ai_assistant')
            ->where('created_at', '>=', $since)
            ->where('properties->tool', 'consult_custom_assistant')
            ->where('event', 'tool_called')
            ->when(! $user->hasRole('admin'), fn ($query) => $query->where('causer_id', $user->id)->where('causer_type', $user->getMorphClass()))
            ->latest()
            ->limit(300)
            ->get()
            ->filter(fn (Activity $activity) => $visible($activity->properties['arguments']['assistant_id'] ?? 0))
            ->map(fn (Activity $activity) => [
                'from' => self::MANAGER_ID,
                'to' => (string) (int) $activity->properties['arguments']['assistant_id'],
                'kind' => 'consult',
                'at' => $activity->created_at,
            ]);

        $handoffs = AiMessage::query()
            ->where('role', AiMessageRole::Tool->value)
            ->where('tool_name', 'handoff_to_assistant')
            ->where('created_at', '>=', $since)
            ->latest('id')
            ->limit(300)
            ->get()
            ->map(function (AiMessage $message) {
                $to = json_decode((string) $message->content, true)['handed_off_profile_id'] ?? null;
                $from = AiMessage::query()
                    ->where('ai_conversation_id', $message->ai_conversation_id)
                    ->where('id', '<', $message->id)
                    ->where('role', AiMessageRole::Assistant->value)
                    ->whereNotNull('ai_assistant_profile_id')
                    ->latest('id')
                    ->value('ai_assistant_profile_id');

                return $to && $from ? ['from' => (string) $from, 'to' => (string) $to, 'kind' => 'handoff', 'at' => $message->created_at] : null;
            })
            ->filter(fn (?array $exchange) => $exchange && $visible($exchange['from']) && $visible($exchange['to']))
            ->values();

        return $consultations->concat($fromManager)->concat($handoffs)->sortByDesc('at')->values();
    }

    /**
     * @param  Collection<int, array{from: string, to: string, kind: string, at: CarbonInterface}>  $exchanges
     * @param  Collection<int, AiAssistantProfile>  $profiles
     * @return list<array{text: string, kind: string, at: string, live: bool}>
     */
    private function feed(Collection $exchanges, Collection $profiles): array
    {
        $name = fn (string $id) => $id === self::MANAGER_ID ? 'دستیار مدیریتی' : $profiles->get((int) $id)?->displayName() ?? 'دستیار';

        return $exchanges->take(12)->map(fn (array $exchange) => [
            'text' => $exchange['kind'] === 'handoff'
                ? '«'.$name($exchange['from']).'» گفتگو را به «'.$name($exchange['to']).'» سپرد'
                : '«'.$name($exchange['from']).'» از «'.$name($exchange['to']).'» مشورت گرفت',
            'kind' => $exchange['kind'],
            'at' => $exchange['at']->diffForHumans(),
            'live' => $exchange['at']->gt(now()->subSeconds(self::LIVE_SECONDS)),
        ])->all();
    }
}
