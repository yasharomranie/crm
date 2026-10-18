<?php

namespace App\Domain\Crm;

use App\Enums\DealSource;
use App\Enums\DealStage;
use App\Enums\LoyaltyTier;
use App\Enums\TaskStatus;
use App\Models\Deal;

/**
 * A transparent 0-100 "how likely is this deal to close, and how soon does
 * it need attention" score for open deals. It is a sum of plain, visible
 * signals (stage, value, how recently anything happened, the customer's
 * history with the business, source, deadlines) - every point comes with a
 * reason, so a salesperson can see why a deal is hot instead of trusting a
 * number. Won and lost deals have no score.
 */
class LeadScorer
{
    public const HOT_AT = 70;

    public const WARM_AT = 40;

    /**
     * @return array{score: ?int, label: ?string, reasons: list<string>}
     */
    public function score(Deal $deal): array
    {
        // A deal just created without a stage still holds the column's DB
        // default only in the database, not on the in-memory model.
        $stage = $deal->stage ?? DealStage::NewLead;

        if (in_array($stage, [DealStage::Won, DealStage::Lost], true)) {
            return ['score' => null, 'label' => null, 'reasons' => []];
        }

        $points = [];

        $points[] = [match ($stage) {
            DealStage::NewLead => 10,
            DealStage::NeedsAssessment => 25,
            DealStage::ProposalSent => 45,
            default => 60,
        }, 'مرحله: '.$stage->getLabel()];

        $value = (int) $deal->value;

        if ($value >= 50_000_000) {
            $points[] = [15, 'ارزش بالای ۵۰ میلیون تومان'];
        } elseif ($value >= 10_000_000) {
            $points[] = [10, 'ارزش بالای ۱۰ میلیون تومان'];
        } elseif ($value >= 1_000_000) {
            $points[] = [5, 'ارزش بالای ۱ میلیون تومان'];
        }

        $idleDays = (int) $deal->updated_at?->diffInDays(now());

        if ($idleDays <= 3) {
            $points[] = [15, 'فعالیت در ۳ روز اخیر'];
        } elseif ($idleDays <= 7) {
            $points[] = [8, 'فعالیت در هفته‌ی اخیر'];
        } elseif ($idleDays > 14) {
            $points[] = [-10, 'بیش از دو هفته بدون فعالیت'];
        }

        $points = [...$points, ...$this->guestSignals($deal), ...$this->sourceSignals($deal), ...$this->deadlineSignals($deal)];

        $score = max(0, min(100, (int) array_sum(array_column($points, 0))));

        return [
            'score' => $score,
            'label' => self::labelFor($score),
            'reasons' => array_map(fn (array $point) => ($point[0] > 0 ? '+' : '').$point[0].' '.$point[1], $points),
        ];
    }

    public static function labelFor(?int $score): ?string
    {
        return match (true) {
            $score === null => null,
            $score >= self::HOT_AT => 'داغ',
            $score >= self::WARM_AT => 'گرم',
            default => 'سرد',
        };
    }

    public static function colorFor(?int $score): string
    {
        return match (true) {
            $score === null => 'gray',
            $score >= self::HOT_AT => 'danger',
            $score >= self::WARM_AT => 'warning',
            default => 'info',
        };
    }

    /**
     * Stores the score without firing model events (so saving a deal can
     * refresh its score without recursing).
     */
    public function refresh(Deal $deal): ?int
    {
        $score = $this->score($deal)['score'];

        // toBase(): an Eloquent update() would also touch updated_at, which
        // would make every rescored deal look freshly worked on.
        Deal::query()->whereKey($deal->id)->toBase()->update(['lead_score' => $score]);
        $deal->setAttribute('lead_score', $score);
        $deal->syncOriginalAttribute('lead_score');

        return $score;
    }

    /**
     * @return int number of open deals re-scored
     */
    public function refreshOpenDeals(): int
    {
        $count = 0;

        Deal::query()
            ->whereNotIn('stage', [DealStage::Won, DealStage::Lost])
            ->with('guest')
            ->chunkById(200, function ($deals) use (&$count) {
                foreach ($deals as $deal) {
                    $this->refresh($deal);
                    $count++;
                }
            });

        return $count;
    }

    /**
     * @return list<array{0: int, 1: string}>
     */
    private function guestSignals(Deal $deal): array
    {
        $guest = $deal->guest;

        if (! $guest) {
            return [];
        }

        $points = [];

        if ($guest->frequency() >= 1) {
            $points[] = [10, 'مشتری قبلی (اقامت تکمیل‌شده دارد)'];
        }

        if (in_array($guest->loyaltyTier(), [LoyaltyTier::Gold, LoyaltyTier::Vip], true)) {
            $points[] = [8, 'مشتری سطح '.$guest->loyaltyTier()->getLabel()];
        }

        if ($guest->tripPlans()->ready()->exists()) {
            $points[] = [5, 'برنامه‌ی سفر ساخته است'];
        }

        if ((int) $guest->wallet_balance > 0) {
            $points[] = [3, 'موجودی کیف پول دارد'];
        }

        if (filled($guest->mobile)) {
            $points[] = [3, 'شماره‌ی تماس ثبت‌شده'];
        }

        return $points;
    }

    /**
     * @return list<array{0: int, 1: string}>
     */
    private function sourceSignals(Deal $deal): array
    {
        return match ($deal->source) {
            DealSource::Referral => [[10, 'منبع: معرفی دوستان']],
            DealSource::WalkIn => [[8, 'منبع: حضوری']],
            DealSource::Phone => [[6, 'منبع: تلفنی']],
            DealSource::Online => [[4, 'منبع: وب‌سایت']],
            default => [],
        };
    }

    /**
     * @return list<array{0: int, 1: string}>
     */
    private function deadlineSignals(Deal $deal): array
    {
        $points = [];

        if ($deal->expected_close_date) {
            if ($deal->expected_close_date->isPast() && ! $deal->expected_close_date->isToday()) {
                $points[] = [-5, 'تاریخ تخمینی قطعی‌شدن گذشته است'];
            } elseif ($deal->expected_close_date->lte(now()->addDays(14))) {
                $points[] = [8, 'قطعی‌شدن تخمینی در دو هفته‌ی آینده'];
            }
        }

        $hasOverdueFollowUp = $deal->followUpTasks()
            ->whereIn('status', [TaskStatus::Todo, TaskStatus::InProgress, TaskStatus::Overdue])
            ->where('due_at', '<', now())
            ->exists();

        if ($hasOverdueFollowUp) {
            $points[] = [-5, 'پیگیری عقب‌افتاده دارد'];
        }

        return $points;
    }
}
