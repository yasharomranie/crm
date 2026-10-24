<?php

namespace App\Domain\Ai\Missions;

use App\Enums\AiMissionMessageKind;
use App\Models\AiMission;
use App\Models\AiMissionMessage;
use Illuminate\Support\Collection;

/**
 * How each member of a mission's team has done across its conversations:
 * how many replies, how many tokens they cost and how the manager rated them.
 * It shows who is expensive or rarely useful, so the team can be trimmed.
 */
class MissionChatStats
{
    /**
     * @return Collection<int, array{role_id: int, name: string, role: string, messages: int, tokens: int, average: int, up: int, down: int}>
     */
    public function forMission(AiMission $mission): Collection
    {
        $mission->loadMissing('roles.profile');

        $rows = AiMissionMessage::query()
            ->join('ai_mission_threads', 'ai_mission_threads.id', '=', 'ai_mission_messages.ai_mission_thread_id')
            ->where('ai_mission_threads.ai_mission_id', $mission->id)
            ->whereNotNull('ai_mission_messages.ai_assistant_profile_id')
            ->groupBy('ai_mission_messages.ai_assistant_profile_id')
            ->selectRaw('ai_mission_messages.ai_assistant_profile_id as profile_id, count(*) as messages, sum(ai_mission_messages.tokens) as tokens, sum(case when ai_mission_messages.rating = 1 then 1 else 0 end) as up, sum(case when ai_mission_messages.rating = -1 then 1 else 0 end) as down')
            ->get()
            ->keyBy('profile_id');

        return $mission->roles
            ->filter(fn ($role) => $role->profile && $rows->has($role->ai_assistant_profile_id))
            ->map(function ($role) use ($rows): array {
                $row = $rows->get($role->ai_assistant_profile_id);
                $messages = (int) $row->messages;

                return [
                    'role_id' => $role->id,
                    'name' => $role->profile->displayName(),
                    'role' => $role->role->getLabel(),
                    'messages' => $messages,
                    'tokens' => (int) $row->tokens,
                    'average' => $messages > 0 ? intdiv((int) $row->tokens, $messages) : 0,
                    'up' => (int) $row->up,
                    'down' => (int) $row->down,
                ];
            })
            ->values();
    }

    /**
     * What a request like this one usually costs here: the average tokens of
     * the mission's earlier conversations with the same depth (or, for a
     * follow-up, of earlier follow-ups). Null until there is something to
     * average.
     *
     * @return array{tokens: int, samples: int}|null
     */
    public function estimate(AiMission $mission, string $depth, bool $followUp): ?array
    {
        $messages = AiMissionMessage::query()
            ->join('ai_mission_threads', 'ai_mission_threads.id', '=', 'ai_mission_messages.ai_mission_thread_id')
            ->where('ai_mission_threads.ai_mission_id', $mission->id)
            ->orderBy('ai_mission_messages.ai_mission_thread_id')
            ->orderBy('ai_mission_messages.id')
            ->limit(2000)
            ->get(['ai_mission_messages.id', 'ai_mission_messages.ai_mission_thread_id', 'ai_mission_messages.kind', 'ai_mission_messages.tokens', 'ai_mission_messages.meta']);

        $turns = [];
        $current = null;

        foreach ($messages as $message) {
            if ($message->kind === AiMissionMessageKind::User) {
                $current = count($turns);
                $turns[] = ['thread' => $message->ai_mission_thread_id, 'depth' => $message->meta['depth'] ?? null, 'follow_up' => (bool) ($message->meta['follow_up'] ?? false), 'tokens' => 0];

                continue;
            }

            if ($current !== null && $turns[$current]['thread'] === $message->ai_mission_thread_id) {
                $turns[$current]['tokens'] += (int) $message->tokens;
            }
        }

        $matching = collect($turns)
            ->filter(fn (array $turn) => $turn['tokens'] > 0 && $turn['follow_up'] === $followUp && ($followUp || $turn['depth'] === $depth))
            ->take(-20);

        if ($matching->isEmpty()) {
            return null;
        }

        return ['tokens' => intdiv((int) $matching->sum('tokens'), $matching->count()), 'samples' => $matching->count()];
    }
}
