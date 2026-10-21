<?php

namespace App\Domain\Ai;

use App\Enums\AiMessageRole;
use App\Enums\AiMissionCardKind;
use App\Enums\AiMissionRoleType;
use App\Enums\AiMissionStatus;
use App\Models\AiAssistantConsultation;
use App\Models\AiAssistantProfile;
use App\Models\AiMessage;
use App\Models\AiMission;
use App\Models\AiMissionCard;
use App\Models\AiMissionRole;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
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
     * @return array<string, mixed>
     */
    public function build(User $user, ?int $missionId = null): array
    {
        $mission = $missionId ? AiMission::query()->ownedBy($user)->with(['roles.profile', 'cards.profile'])->find($missionId) : null;

        if ($mission) {
            return $this->buildForMission($mission, $this->missionChoices($user));
        }

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

        $replies = $this->latestReplies($user, $profiles);
        $given = $exchanges->where('kind', 'consult')->countBy('to');
        $asked = $exchanges->where('kind', 'consult')->countBy('from');
        $liveIds = collect($edges)->where('live', true)->flatMap(fn (array $edge) => [$edge['source'], $edge['target']])->unique();

        $lastSeen = fn (string $id) => $exchanges->first(fn (array $exchange) => $exchange['from'] === $id || $exchange['to'] === $id)['at'] ?? null;

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
            'last_seen' => $lastSeen((string) $profile->id)?->diffForHumans(),
            'reply' => $replies[(string) $profile->id] ?? null,
        ])->values()->prepend([
            'id' => self::MANAGER_ID,
            'label' => 'دستیار مدیریتی',
            'color' => '#7dd3fc',
            'shape' => 'manager',
            'consultable' => false,
            'specialties' => [],
            'messages' => 0,
            'consulted' => 0,
            'asked' => $exchanges->where('from', self::MANAGER_ID)->count(),
            'live' => $liveIds->contains(self::MANAGER_ID),
            'last_seen' => $lastSeen(self::MANAGER_ID)?->diffForHumans(),
            'reply' => $replies[self::MANAGER_ID] ?? null,
        ])->all();

        return [
            'mission' => null,
            'missions' => $this->missionChoices($user),
            'nodes' => $nodes,
            'edges' => $edges,
            'feed' => $this->feed($exchanges, $profiles),
            'totals' => [
                'assistants' => $profiles->count(),
                'consultations' => $exchanges->where('kind', 'consult')->count(),
                'handoffs' => $exchanges->where('kind', 'handoff')->count(),
                'live' => count($liveIds),
            ],
            'labels' => ['دستیار در تیم', 'مشورت · ۳۰ روز', 'تحویل گفتگو', 'فعال · ۵ دقیقه'],
            'legend' => [
                ['color' => '#38bdf8', 'label' => 'مشورت'],
                ['color' => '#fbbf24', 'label' => 'تحویل گفتگو'],
            ],
        ];
    }

    /**
     * The same map for one mission: only its team, laid out around the
     * coordinator, with the board's findings flowing to the critic (or
     * straight to the coordinator when there is none) and on to the report.
     *
     * @param  list<array{id: int, title: string, status: string}>  $choices
     * @return array<string, mixed>
     */
    private function buildForMission(AiMission $mission, array $choices): array
    {
        $running = $mission->status === AiMissionStatus::Running;
        $members = $mission->roles->filter(fn (AiMissionRole $role) => $role->profile)->values();
        $coordinatorId = $members->firstWhere('role', AiMissionRoleType::Coordinator)?->ai_assistant_profile_id;
        $criticId = $members->firstWhere('role', AiMissionRoleType::Critic)?->ai_assistant_profile_id;

        $nodes = $members->map(function (AiMissionRole $role) use ($mission, $running, $coordinatorId) {
            $profile = $role->profile;
            $own = $mission->cards->where('ai_assistant_profile_id', $profile->id);
            $last = $own->sortByDesc('id')->first();
            $plain = $last ? trim((string) preg_replace('/\s+/u', ' ', $last->claim)) : null;

            return [
                'id' => (string) $profile->id,
                'label' => $profile->displayName(),
                'color' => self::COLORS[$profile->avatar_color] ?? self::COLORS['Amber'],
                'shape' => $profile->avatar_shape,
                'consultable' => (bool) $profile->consultable,
                'specialties' => [],
                'messages' => $own->count(),
                'consulted' => $own->where('kind', AiMissionCardKind::Finding)->count(),
                'asked' => $own->where('kind', AiMissionCardKind::Critique)->count(),
                'live' => $running,
                'last_seen' => $last?->created_at->diffForHumans(),
                'reply' => $last ? ['text' => Str::limit($plain, 110), 'full' => Str::limit($plain, 500), 'at' => $last->created_at->diffForHumans()] : null,
                'role' => $role->role->value,
                'role_label' => $role->role->getLabel(),
                'hub' => $profile->id === $coordinatorId,
            ];
        })->all();

        $edges = [];
        foreach ($members->where('role', AiMissionRoleType::Specialist) as $role) {
            $target = $criticId ?? $coordinatorId;
            $count = $mission->cards->where('ai_assistant_profile_id', $role->ai_assistant_profile_id)->where('kind', AiMissionCardKind::Finding)->count();

            if ($target) {
                $edges[] = ['id' => $role->ai_assistant_profile_id.'>'.$target.'>report', 'source' => (string) $role->ai_assistant_profile_id, 'target' => (string) $target, 'kind' => 'report', 'count' => max($count, 1), 'live' => $running];
            }
        }
        if ($criticId && $coordinatorId) {
            $edges[] = ['id' => $criticId.'>'.$coordinatorId.'>review', 'source' => (string) $criticId, 'target' => (string) $coordinatorId, 'kind' => 'review', 'count' => max($mission->cards->where('kind', AiMissionCardKind::Critique)->count(), 1), 'live' => $running];
        }

        $names = $members->mapWithKeys(fn (AiMissionRole $role) => [$role->ai_assistant_profile_id => $role->profile->displayName()]);
        $feed = $mission->cards->sortByDesc('id')->take(12)->map(fn (AiMissionCard $card) => [
            'text' => '«'.($names[$card->ai_assistant_profile_id] ?? 'دستیار').'» '.match ($card->kind) {
                AiMissionCardKind::Finding => 'یافته ثبت کرد: '.Str::limit($card->claim, 90),
                AiMissionCardKind::Critique => 'نقد کرد: '.Str::limit($card->claim, 90),
                AiMissionCardKind::Summary => 'جمع‌بندی نوشت.',
            },
            'kind' => $card->kind === AiMissionCardKind::Critique ? 'handoff' : 'consult',
            'at' => $card->created_at->diffForHumans(),
            'live' => $card->created_at->gt(now()->subSeconds(self::LIVE_SECONDS)),
        ])->values()->all();

        return [
            'mission' => ['id' => $mission->id, 'title' => $mission->title, 'status' => $mission->status->getLabel(), 'color' => $mission->status->getColor()],
            'missions' => $choices,
            'nodes' => $nodes,
            'edges' => $edges,
            'feed' => $feed,
            'totals' => [
                'assistants' => count($nodes),
                'consultations' => $mission->cards->where('kind', AiMissionCardKind::Finding)->count(),
                'handoffs' => $mission->cards->where('kind', AiMissionCardKind::Critique)->count(),
                'live' => $mission->rounds_run,
            ],
            'labels' => ['عضو تیم', 'یافته روی تخته', 'نقد', 'دور اجرا‌شده'],
            'legend' => [
                ['color' => '#34d399', 'label' => 'گزارش یافته'],
                ['color' => '#fb7185', 'label' => 'بازبینی منتقد'],
            ],
        ];
    }

    /**
     * @return list<array{id: int, title: string, status: string}>
     */
    private function missionChoices(User $user): array
    {
        return AiMission::query()->ownedBy($user)->latest('id')->limit(30)->get()
            ->map(fn (AiMission $mission) => ['id' => $mission->id, 'title' => $mission->title, 'status' => $mission->status->getLabel()])
            ->all();
    }

    /**
     * The last thing each assistant said to this user, shown as a speech
     * bubble. Only the user's own conversations are read, so a shared
     * assistant never reveals what someone else asked it.
     *
     * @param  Collection<int, AiAssistantProfile>  $profiles  keyed by id
     * @return array<string, array{text: string, full: string, at: string}>
     */
    private function latestReplies(User $user, Collection $profiles): array
    {
        $replies = [];

        AiMessage::query()
            ->where('role', AiMessageRole::Assistant->value)
            ->whereNotNull('content')
            ->whereHas('conversation', fn ($query) => $query->where('user_id', $user->id))
            ->with('conversation:id,ai_assistant_profile_id,user_id')
            ->latest('id')
            ->limit(300)
            ->get()
            ->each(function (AiMessage $message) use (&$replies, $profiles) {
                $owner = $message->ai_assistant_profile_id ?? $message->conversation->ai_assistant_profile_id;
                $key = $owner === null ? self::MANAGER_ID : (string) $owner;

                if (isset($replies[$key]) || ($owner !== null && ! $profiles->has((int) $owner))) {
                    return;
                }

                $plain = trim((string) preg_replace('/\s+/u', ' ', (string) preg_replace('/[#*`|>_~]+/u', ' ', $message->content)));

                if ($plain === '') {
                    return;
                }

                $replies[$key] = [
                    'text' => Str::limit($plain, 110),
                    'full' => Str::limit($plain, 500),
                    'at' => $message->created_at->diffForHumans(),
                ];
            });

        return $replies;
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
