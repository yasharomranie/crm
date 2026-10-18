<?php

namespace App\Domain\Ticketing;

use App\Models\Gate;
use App\Models\TicketUsage;

/**
 * "How many people are inside right now", from today's successful passes:
 * a pass at an entry gate adds one, a pass at an exit gate removes one.
 * Gates set to "entry and exit" cannot tell which way a person went, so
 * they are counted in the day's total but not in the inside figure.
 */
class GateOccupancy
{
    /**
     * @return array{inside: int, entries: int, exits: int, total_passes: int, by_gate: list<array{name: string, kind: string, passes: int}>}
     */
    public function today(): array
    {
        $passesByGate = TicketUsage::query()
            ->where('created_at', '>=', now()->startOfDay())
            ->groupBy('gate_id')
            ->selectRaw('gate_id, count(*) as passes')
            ->pluck('passes', 'gate_id');

        $gates = Gate::query()->whereIn('id', $passesByGate->keys())->get()->keyBy('id');

        $entries = 0;
        $exits = 0;
        $byGate = [];

        foreach ($passesByGate as $gateId => $passes) {
            $gate = $gates[$gateId] ?? null;

            if (! $gate) {
                continue;
            }

            if ($gate->kind === 'entry') {
                $entries += (int) $passes;
            } elseif ($gate->kind === 'exit') {
                $exits += (int) $passes;
            }

            $byGate[] = ['name' => $gate->name, 'kind' => $gate->kind, 'passes' => (int) $passes];
        }

        return [
            'inside' => max(0, $entries - $exits),
            'entries' => $entries,
            'exits' => $exits,
            'total_passes' => (int) $passesByGate->sum(),
            'by_gate' => $byGate,
        ];
    }
}
