<?php

namespace App\Domain\Crm;

use App\Models\Guest;
use App\Support\MobileNumber;
use Illuminate\Support\Collection;

/**
 * Finds guest records that are probably the same person. The database
 * already forbids two guests with the *identical* mobile/email/national
 * code, so what actually piles up is the same value written differently
 * (+98912..., 0912..., Persian digits, an upper-case email) and
 * half-filled records - which is what the normalized comparisons below
 * catch. This only suggests: nothing is merged without a person confirming
 * (see GuestMergeService).
 */
class GuestDuplicateDetector
{
    public const REASON_MOBILE = 'شماره‌ی موبایل یکسان';

    public const REASON_EMAIL = 'ایمیل یکسان';

    public const REASON_NAME = 'نام یکسان (یکی از پرونده‌ها شماره‌ی تماس ندارد)';

    /**
     * @return Collection<int, array{guests: Collection<int, Guest>, reasons: list<string>}>
     */
    public function findGroups(): Collection
    {
        $guests = Guest::query()->orderBy('id')->get(['id', 'name', 'mobile', 'email'])->keyBy('id');

        $parent = $guests->keys()->mapWithKeys(fn (int $id) => [$id => $id])->all();
        $reasonsByPair = [];

        $union = function (int $a, int $b, string $reason) use (&$parent, &$reasonsByPair) {
            $rootA = $this->root($parent, $a);
            $rootB = $this->root($parent, $b);

            if ($rootA !== $rootB) {
                $parent[max($rootA, $rootB)] = min($rootA, $rootB);
            }

            $reasonsByPair[$a][$reason] = true;
            $reasonsByPair[$b][$reason] = true;
        };

        $this->unionBuckets($guests, fn (Guest $guest) => static::normalizeMobile($guest->mobile), self::REASON_MOBILE, $union);
        $this->unionBuckets($guests, fn (Guest $guest) => static::normalizeEmail($guest->email), self::REASON_EMAIL, $union);

        // Names are common, so only a pair where one record can't be told
        // apart by phone number counts, and only when exactly two share it.
        $nameBuckets = $guests->groupBy(fn (Guest $guest) => static::normalizeName($guest->name))->forget('');

        foreach ($nameBuckets as $bucket) {
            if ($bucket->count() !== 2) {
                continue;
            }

            [$first, $second] = $bucket->values()->all();

            if (static::normalizeMobile($first->mobile) !== null && static::normalizeMobile($second->mobile) !== null) {
                continue;
            }

            $union($first->id, $second->id, self::REASON_NAME);
        }

        return $guests
            ->groupBy(fn (Guest $guest) => $this->root($parent, $guest->id))
            ->filter(fn (Collection $members) => $members->count() > 1)
            ->map(fn (Collection $members) => [
                'guests' => $members->values(),
                'reasons' => $members
                    ->flatMap(fn (Guest $guest) => array_keys($reasonsByPair[$guest->id] ?? []))
                    ->unique()
                    ->values()
                    ->all(),
            ])
            ->values();
    }

    /**
     * 09123456789 for every common way of writing one Iranian mobile
     * number, or null if it isn't one.
     */
    public static function normalizeMobile(?string $mobile): ?string
    {
        return MobileNumber::normalize($mobile);
    }

    public static function normalizeEmail(?string $email): ?string
    {
        $email = mb_strtolower(trim((string) $email));

        return $email === '' ? null : $email;
    }

    /**
     * Folds the spelling differences that make one name look like two:
     * Arabic vs Persian ي/ك, spaces and zero-width non-joiners ("محمد رضا"
     * vs "محمدرضا"), diacritics, letter case.
     */
    public static function normalizeName(?string $name): string
    {
        $name = strtr((string) $name, ['ي' => 'ی', 'ك' => 'ک', 'ة' => 'ه', 'ۀ' => 'ه']);
        $name = preg_replace('/[\s\x{200C}\x{200D}\x{064B}-\x{0652}]+/u', '', $name);

        return mb_strtolower($name);
    }

    /**
     * @param  Collection<int, Guest>  $guests
     * @param  \Closure(Guest): ?string  $keyFor
     * @param  \Closure(int, int, string): void  $union
     */
    private function unionBuckets(Collection $guests, \Closure $keyFor, string $reason, \Closure $union): void
    {
        $guests
            ->groupBy(fn (Guest $guest) => $keyFor($guest) ?? '')
            ->forget('')
            ->each(function (Collection $bucket) use ($reason, $union) {
                if ($bucket->count() < 2) {
                    return;
                }

                $ids = $bucket->pluck('id')->all();

                foreach (array_slice($ids, 1) as $id) {
                    $union($ids[0], $id, $reason);
                }
            });
    }

    /**
     * @param  array<int, int>  $parent
     */
    private function root(array $parent, int $id): int
    {
        while ($parent[$id] !== $id) {
            $id = $parent[$id];
        }

        return $id;
    }
}
