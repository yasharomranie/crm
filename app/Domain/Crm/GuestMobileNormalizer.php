<?php

namespace App\Domain\Crm;

use App\Models\Guest;
use App\Support\MobileNumber;

/**
 * Rewrites the mobile of existing customers to the one canonical spelling.
 * A customer whose canonical number already belongs to another record is
 * reported as a conflict and left alone - that is the same person under two
 * spellings and is merged by a person (GuestMergeService), never silently.
 */
class GuestMobileNormalizer
{
    /**
     * @return array{changed: list<array{id: int, from: string, to: string}>, conflicts: list<array{id: int, mobile: string, canonical: string, other_id: int}>}
     */
    public function run(bool $apply): array
    {
        $changed = [];
        $conflicts = [];

        $taken = Guest::query()->whereNotNull('mobile')->pluck('id', 'mobile')->all();

        Guest::query()->whereNotNull('mobile')->orderBy('id')->each(function (Guest $guest) use ($apply, &$changed, &$conflicts, &$taken) {
            $current = (string) $guest->getRawOriginal('mobile');
            $canonical = MobileNumber::canonical($current);

            if ($canonical === null || $canonical === $current) {
                return;
            }

            $otherId = $taken[$canonical] ?? null;

            if ($otherId !== null && $otherId !== $guest->id) {
                $conflicts[] = ['id' => $guest->id, 'mobile' => $current, 'canonical' => $canonical, 'other_id' => $otherId];

                return;
            }

            if ($apply) {
                $guest->newQuery()->whereKey($guest->id)->toBase()->update(['mobile' => $canonical]);
            }

            unset($taken[$current]);
            $taken[$canonical] = $guest->id;
            $changed[] = ['id' => $guest->id, 'from' => $current, 'to' => $canonical];
        });

        return ['changed' => $changed, 'conflicts' => $conflicts];
    }
}
