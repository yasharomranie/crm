<?php

namespace App\Domain\Crm;

use App\Enums\BookingStatus;
use App\Enums\DealStage;
use App\Enums\TicketStatus;
use App\Models\Guest;
use App\Models\GuestSegment;
use Illuminate\Database\Eloquent\Builder;

/**
 * Turns a GuestSegment's stored rules into a guests query.
 *
 * A segment with no rules matches nobody (an "all customers" list is the
 * plain guests table), and a rule of an unknown type matches nobody as
 * well, so a stale or hand-edited rule can never widen a list - and with
 * it, a campaign - by accident.
 */
class GuestSegmentQuery
{
    public const TYPE_COMPLETED_STAYS = 'completed_stays';

    public const TYPE_NEVER_STAYED = 'never_stayed';

    public const TYPE_DAYS_SINCE_LAST_STAY = 'days_since_last_stay';

    public const TYPE_WALLET_BALANCE = 'wallet_balance';

    public const TYPE_HAS_TAG = 'has_tag';

    public const TYPE_OPEN_DEAL = 'open_deal';

    public const TYPE_OPEN_TICKET = 'open_ticket';

    public const TYPE_REGISTERED_WITHIN_DAYS = 'registered_within_days';

    public const TYPE_REGISTERED_BEFORE_DAYS = 'registered_before_days';

    public const TYPE_HAS_EMAIL = 'has_email';

    public const TYPE_HAS_MOBILE = 'has_mobile';

    private const OPERATORS = ['gte' => '>=', 'lte' => '<=', 'eq' => '='];

    /**
     * @return array<string, string> type => Persian label
     */
    public static function typeLabels(): array
    {
        return [
            self::TYPE_COMPLETED_STAYS => 'تعداد اقامت‌های تکمیل‌شده',
            self::TYPE_NEVER_STAYED => 'هیچ اقامت تکمیل‌شده‌ای ندارد',
            self::TYPE_DAYS_SINCE_LAST_STAY => 'فاصلهٔ آخرین اقامت (روز)',
            self::TYPE_WALLET_BALANCE => 'موجودی کیف پول (تومان)',
            self::TYPE_HAS_TAG => 'برچسب دارد',
            self::TYPE_OPEN_DEAL => 'معاملهٔ باز دارد',
            self::TYPE_OPEN_TICKET => 'تیکت باز دارد',
            self::TYPE_REGISTERED_WITHIN_DAYS => 'ثبت‌شده در N روز اخیر',
            self::TYPE_REGISTERED_BEFORE_DAYS => 'ثبت‌شده بیش از N روز پیش',
            self::TYPE_HAS_EMAIL => 'ایمیل دارد',
            self::TYPE_HAS_MOBILE => 'موبایل دارد',
        ];
    }

    /**
     * @return list<string> types compared with an operator and a number
     */
    public static function numericTypes(): array
    {
        return [self::TYPE_COMPLETED_STAYS, self::TYPE_WALLET_BALANCE];
    }

    /**
     * @return list<string> types that need only a number of days
     */
    public static function dayCountTypes(): array
    {
        return [self::TYPE_DAYS_SINCE_LAST_STAY, self::TYPE_REGISTERED_WITHIN_DAYS, self::TYPE_REGISTERED_BEFORE_DAYS];
    }

    /**
     * @return list<string> types answered with yes/no
     */
    public static function yesNoTypes(): array
    {
        return [self::TYPE_OPEN_DEAL, self::TYPE_OPEN_TICKET, self::TYPE_HAS_EMAIL, self::TYPE_HAS_MOBILE];
    }

    /**
     * @return Builder<Guest>
     */
    public function query(GuestSegment $segment): Builder
    {
        $query = Guest::query();
        $conditions = array_values(array_filter((array) $segment->conditions, 'is_array'));

        if ($conditions === []) {
            return $query->whereRaw('1 = 0');
        }

        $boolean = $segment->match_type === 'any' ? 'or' : 'and';

        return $query->where(function (Builder $group) use ($conditions, $boolean) {
            foreach ($conditions as $condition) {
                $group->where(fn (Builder $clause) => $this->applyCondition($clause, $condition), null, null, $boolean);
            }
        });
    }

    public function count(GuestSegment $segment): int
    {
        return $this->query($segment)->count();
    }

    /**
     * @param  Builder<Guest>  $query
     * @param  array<string, mixed>  $condition
     */
    private function applyCondition(Builder $query, array $condition): void
    {
        $type = (string) ($condition['type'] ?? '');
        $value = $condition['value'] ?? null;
        $operator = self::OPERATORS[$condition['operator'] ?? 'gte'] ?? '>=';
        $isYes = ($condition['answer'] ?? $value) === 'yes';

        match ($type) {
            self::TYPE_COMPLETED_STAYS => is_numeric($value)
                ? $query->has('bookings', $operator, (int) $value, 'and', fn (Builder $bookings) => $bookings->where('status', BookingStatus::CheckedOut))
                : $query->whereRaw('1 = 0'),
            self::TYPE_NEVER_STAYED => $query->whereDoesntHave('bookings', fn (Builder $bookings) => $bookings->where('status', BookingStatus::CheckedOut)),
            self::TYPE_DAYS_SINCE_LAST_STAY => is_numeric($value)
                ? $query
                    ->whereHas('bookings', fn (Builder $bookings) => $bookings->where('status', BookingStatus::CheckedOut))
                    ->whereDoesntHave('bookings', fn (Builder $bookings) => $bookings
                        ->where('status', BookingStatus::CheckedOut)
                        ->where('actual_check_out_at', '>=', now()->subDays((int) $value)))
                : $query->whereRaw('1 = 0'),
            self::TYPE_WALLET_BALANCE => is_numeric($value)
                ? $query->where('wallet_balance', $operator, (int) $value)
                : $query->whereRaw('1 = 0'),
            self::TYPE_HAS_TAG => filled($condition['tag_id'] ?? null)
                ? $query->whereHas('tags', fn (Builder $tags) => $tags->where('tags.id', (int) $condition['tag_id']))
                : $query->whereRaw('1 = 0'),
            self::TYPE_OPEN_DEAL => $this->applyExistence($query, 'deals', $isYes, fn (Builder $deals) => $deals->whereNotIn('stage', [DealStage::Won, DealStage::Lost])),
            self::TYPE_OPEN_TICKET => $this->applyExistence($query, 'tickets', $isYes, fn (Builder $tickets) => $tickets->whereIn('status', [TicketStatus::Open, TicketStatus::InProgress])),
            self::TYPE_REGISTERED_WITHIN_DAYS => is_numeric($value)
                ? $query->where('created_at', '>=', now()->subDays((int) $value))
                : $query->whereRaw('1 = 0'),
            self::TYPE_REGISTERED_BEFORE_DAYS => is_numeric($value)
                ? $query->where('created_at', '<', now()->subDays((int) $value))
                : $query->whereRaw('1 = 0'),
            self::TYPE_HAS_EMAIL => $this->applyFilled($query, 'email', $isYes),
            self::TYPE_HAS_MOBILE => $this->applyFilled($query, 'mobile', $isYes),
            default => $query->whereRaw('1 = 0'),
        };
    }

    /**
     * @param  Builder<Guest>  $query
     */
    private function applyExistence(Builder $query, string $relation, bool $wanted, \Closure $constraint): void
    {
        $wanted ? $query->whereHas($relation, $constraint) : $query->whereDoesntHave($relation, $constraint);
    }

    /**
     * @param  Builder<Guest>  $query
     */
    private function applyFilled(Builder $query, string $column, bool $wanted): void
    {
        $wanted
            ? $query->whereNotNull($column)->where($column, '!=', '')
            : $query->where(fn (Builder $empty) => $empty->whereNull($column)->orWhere($column, ''));
    }
}
