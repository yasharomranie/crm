<?php

namespace App\Filament\Widgets;

use App\Domain\Hospitality\RoomInventory;
use App\Enums\AttendanceStatus;
use App\Enums\BookingStatus;
use App\Enums\DealStage;
use App\Enums\FormSubmissionStatus;
use App\Enums\TaskStatus;
use App\Enums\WorkflowInstanceStatus;
use App\Models\AttendanceRecord;
use App\Models\Booking;
use App\Models\BookingCharge;
use App\Models\Deal;
use App\Models\FormSubmission;
use App\Models\Task;
use App\Models\WorkflowInstance;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;
use Illuminate\Support\Number;

/**
 * The dashboard's landing row of "interesting facts" - one glanceable stat
 * per major module (accommodation, finance, workflow, tasks, HR), replacing
 * Filament's default AccountWidget/FilamentInfoWidget placeholders.
 */
class GanjnamehStatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = -2;

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        return [
            $this->occupancyStat(),
            $this->revenueStat(),
            $this->pipelineStat(),
            $this->pendingApprovalsStat(),
            $this->overdueTasksStat(),
            $this->attendanceStat(),
        ];
    }

    private function occupancyStat(): Stat
    {
        $inventory = app(RoomInventory::class)->summary();
        $totalRooms = $inventory['sellable'];
        $occupied = $inventory['occupied'];
        $rate = $totalRooms > 0 ? (int) round($occupied / $totalRooms * 100) : 0;

        $trend = collect(range(6, 0))->map(function (int $daysAgo) use ($totalRooms) {
            if ($totalRooms === 0) {
                return 0;
            }

            $day = today()->subDays($daysAgo);

            $occupiedThatDay = Booking::where('status', '!=', BookingStatus::Cancelled)
                ->where('check_in_date', '<=', $day)
                ->where('check_out_date', '>', $day)
                ->count();

            return (int) round($occupiedThatDay / $totalRooms * 100);
        });

        return Stat::make('نرخ اشغال امروز', Number::toPersian("{$rate}٪"))
            ->description(Number::toPersian("{$occupied} از {$totalRooms} واحد پر است"))
            ->descriptionIcon(Heroicon::OutlinedHomeModern)
            ->color($rate >= 70 ? 'success' : ($rate >= 40 ? 'warning' : 'gray'))
            ->chart($trend->all())
            ->chartColor('primary');
    }

    private function revenueStat(): Stat
    {
        $thisMonth = $this->monthlyAccommodationRevenue(now());
        $lastMonth = $this->monthlyAccommodationRevenue(now()->subMonthNoOverflow());

        $changePercent = $lastMonth > 0 ? (int) round((($thisMonth - $lastMonth) / $lastMonth) * 100) : null;

        $description = match (true) {
            $changePercent === null => 'ماه گذشته دادهٔ کافی برای مقایسه نبود',
            $changePercent >= 0 => Number::toPersian("{$changePercent}٪ بیشتر از ماه قبل"),
            default => Number::toPersian(abs($changePercent).'٪ کمتر از ماه قبل'),
        };

        return Stat::make('درآمد اقامتی این ماه', Number::toPersian(number_format($thisMonth)).' تومان')
            ->description($description)
            ->descriptionIcon(
                $changePercent !== null && $changePercent < 0
                    ? Heroicon::OutlinedArrowTrendingDown
                    : Heroicon::OutlinedArrowTrendingUp
            )
            ->descriptionColor($changePercent !== null && $changePercent < 0 ? 'danger' : 'success')
            ->color('primary');
    }

    private function monthlyAccommodationRevenue(Carbon $month): int
    {
        return (int) BookingCharge::whereHas('booking', function ($query) use ($month) {
            $query->where('status', BookingStatus::CheckedOut)
                ->whereMonth('actual_check_out_at', $month->month)
                ->whereYear('actual_check_out_at', $month->year);
        })->get()->sum(fn (BookingCharge $charge) => $charge->quantity * $charge->unit_amount);
    }

    private function pipelineStat(): Stat
    {
        $openStages = [DealStage::NewLead, DealStage::NeedsAssessment, DealStage::ProposalSent, DealStage::Negotiation];
        $openDeals = Deal::whereIn('stage', $openStages)->get();

        return Stat::make('ارزش پایپ‌لاین باز', Number::toPersian(number_format((int) $openDeals->sum('value'))).' تومان')
            ->description(Number::toPersian($openDeals->count().' معاملهٔ در جریان'))
            ->descriptionIcon(Heroicon::OutlinedChartBar)
            ->color('info');
    }

    private function pendingApprovalsStat(): Stat
    {
        $pending = FormSubmission::where('status', FormSubmissionStatus::PendingApproval)->count();

        // Same SLA-breach stand-in as the AI assistant's own tool: updated_at
        // is bumped exactly when a workflow instance advances to a new step.
        $breached = WorkflowInstance::query()
            ->where('status', WorkflowInstanceStatus::InProgress)
            ->with('currentStep')
            ->get()
            ->filter(fn (WorkflowInstance $instance) => $instance->currentStep?->sla_hours
                && $instance->updated_at->diffInHours(now()) > $instance->currentStep->sla_hours)
            ->count();

        return Stat::make('فرم‌های در انتظار تأیید', Number::toPersian($pending))
            ->description($breached > 0 ? Number::toPersian("{$breached} مورد از مهلت SLA گذشته") : 'همه در بازهٔ زمانی مجازند')
            ->descriptionIcon($breached > 0 ? Heroicon::OutlinedExclamationTriangle : Heroicon::OutlinedCheckCircle)
            ->descriptionColor($breached > 0 ? 'danger' : 'success')
            ->color($pending > 0 ? 'warning' : 'success');
    }

    private function overdueTasksStat(): Stat
    {
        $openTasks = Task::whereNotIn('status', [TaskStatus::Done, TaskStatus::Cancelled]);

        $overdue = (clone $openTasks)->whereNotNull('due_at')->where('due_at', '<', now())->count();
        $openTotal = $openTasks->count();

        return Stat::make('تسک‌های عقب‌افتاده', Number::toPersian($overdue))
            ->description(Number::toPersian("از مجموع {$openTotal} تسک باز"))
            ->descriptionIcon(Heroicon::OutlinedClock)
            ->color($overdue > 0 ? 'danger' : 'success');
    }

    private function attendanceStat(): Stat
    {
        $today = AttendanceRecord::whereDate('date', today())->get();
        $onTime = $today->where('status', AttendanceStatus::OnTime)->count();
        $late = $today->where('status', AttendanceStatus::Late)->count();
        $absent = $today->where('status', AttendanceStatus::Absent)->count();

        return Stat::make('حضور امروز', Number::toPersian(($onTime + $late).' نفر حاضر'))
            ->description(Number::toPersian("{$late} نفر تأخیر • {$absent} نفر غایب"))
            ->descriptionIcon(Heroicon::OutlinedUserGroup)
            ->descriptionColor($late + $absent > 0 ? 'warning' : 'success')
            ->color('gray');
    }
}
