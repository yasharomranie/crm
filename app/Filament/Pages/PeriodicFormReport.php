<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HasAccessControlledPage;
use App\Filament\Resources\FormSubmissions\FormSubmissionResource;
use App\Models\FormSubmission;
use App\Models\FormTemplate;
use App\Models\User;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Morilog\Jalali\Jalalian;

/**
 * Rolls up an employee's individually-submitted, per-day form submissions
 * (e.g. a daily checklist) into one weekly or monthly calendar view - the
 * "چک‌لیست روزانه" pattern: each day is its own FormSubmission row (see
 * FormSubmissionResource), and this page is purely a read-only reporting
 * lens over them, grouped by the real Jalali week/month. Clicking a filled
 * day opens that day's submission on FormSubmissionResource's own view page.
 */
class PeriodicFormReport extends Page
{
    use HasAccessControlledPage;

    protected string $view = 'filament.pages.periodic-form-report';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|\UnitEnum|null $navigationGroup = 'فرم‌ساز و گردش‌کار';

    protected static ?string $navigationLabel = 'گزارش ادواری فرم‌ها';

    protected static ?string $title = 'گزارش ادواری فرم‌ها (روزانه در قالب هفتگی/ماهانه)';

    public static function accessControlPermission(): string
    {
        return 'periodic-form-report.access';
    }

    public ?int $userId = null;

    public ?int $formTemplateId = null;

    public string $periodType = 'monthly';

    public string $anchorDate;

    public function mount(): void
    {
        $this->userId = auth()->id();
        $this->anchorDate = now()->toDateString();
    }

    /**
     * Only someone who can already see other people's submissions
     * (admin, or the "form-submissions.view_any" permission) may point
     * this report at anyone but themselves - enforced again in
     * resolvedUserId(), not just hidden in the UI, since $userId is a
     * public Livewire property a client request could otherwise tamper
     * with.
     */
    public function canSelectOtherUsers(): bool
    {
        return auth()->user()->hasRole('admin') || auth()->user()->can('form-submissions.view_any');
    }

    public function getUserOptions(): Collection
    {
        return User::query()->orderBy('name')->pluck('name', 'id');
    }

    public function getFormTemplateOptions(): Collection
    {
        return FormTemplate::query()->where('is_active', true)->orderBy('title')->pluck('title', 'id');
    }

    /**
     * @return array<int, string>
     */
    public function getWeekdayHeaders(): array
    {
        return ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه'];
    }

    public function previousPeriod(): void
    {
        $anchor = $this->jalaliAnchor();

        $this->anchorDate = ($this->periodType === 'weekly' ? $anchor->subDays(7) : $anchor->subMonths(1))
            ->toCarbon()->toDateString();
    }

    public function nextPeriod(): void
    {
        $anchor = $this->jalaliAnchor();

        $this->anchorDate = ($this->periodType === 'weekly' ? $anchor->addDays(7) : $anchor->addMonths(1))
            ->toCarbon()->toDateString();
    }

    public function goToToday(): void
    {
        $this->anchorDate = now()->toDateString();
    }

    public function getPeriodLabel(): string
    {
        if ($this->periodType === 'weekly') {
            return $this->getPeriodStart()->format('%d %B').' تا '.$this->getPeriodEnd()->format('%d %B %Y');
        }

        return $this->getPeriodStart()->format('%B %Y');
    }

    /**
     * The calendar grid, chunked into weeks of 7 days each (Saturday to
     * Friday) so the Blade view can render it as table rows. In monthly
     * mode this naturally pads the first/last week with days from the
     * neighbouring months - those are flagged inPeriod=false and rendered
     * muted rather than dropped, exactly like a normal calendar widget.
     *
     * @return array<int, array<int, array{
     *     jalali: Jalalian,
     *     carbon: Carbon,
     *     inPeriod: bool,
     *     isToday: bool,
     *     isFuture: bool,
     *     submission: ?FormSubmission,
     * }>>
     */
    public function getWeeks(): array
    {
        return array_chunk($this->buildDays(), 7);
    }

    /**
     * @return array<int, array{
     *     jalali: Jalalian,
     *     carbon: Carbon,
     *     inPeriod: bool,
     *     isToday: bool,
     *     isFuture: bool,
     *     submission: ?FormSubmission,
     * }>
     */
    private function buildDays(): array
    {
        $start = $this->getPeriodStart();
        $end = $this->getPeriodEnd();

        $gridStart = $start->getFirstDayOfWeek();
        $gridEnd = $end->getEndDayOfWeek();

        $periodStartCarbon = $start->toCarbon()->startOfDay();
        $periodEndCarbon = $end->toCarbon()->startOfDay();
        $today = Carbon::today();

        $submissions = $this->submissionsByDate();

        $days = [];
        $cursor = $gridStart;

        while ($cursor->lessThanOrEqualsToCarbon($gridEnd->toCarbon())) {
            $carbon = $cursor->toCarbon()->startOfDay();
            $dateKey = $carbon->toDateString();

            $days[] = [
                'jalali' => $cursor,
                'carbon' => $carbon,
                'inPeriod' => $carbon->gte($periodStartCarbon) && $carbon->lte($periodEndCarbon),
                'isToday' => $carbon->isSameDay($today),
                'isFuture' => $carbon->isFuture(),
                'submission' => $submissions->get($dateKey),
            ];

            $cursor = $cursor->addDays(1);
        }

        return $days;
    }

    /**
     * One row per calendar day in the visible period, keyed by Gregorian
     * date string; when the same day carries more than one submission of
     * the chosen template (e.g. a resubmission), the most recent one wins.
     *
     * @return Collection<string, FormSubmission>
     */
    private function submissionsByDate(): Collection
    {
        if (! $this->formTemplateId) {
            return collect();
        }

        $start = $this->getPeriodStart()->getFirstDayOfWeek()->toCarbon()->startOfDay();
        $end = $this->getPeriodEnd()->getEndDayOfWeek()->toCarbon()->endOfDay();

        return FormSubmission::query()
            ->where('form_template_id', $this->formTemplateId)
            ->where('submitted_by', $this->resolvedUserId())
            ->whereBetween('submitted_at', [$start, $end])
            ->orderByDesc('submitted_at')
            ->get()
            ->unique(fn (FormSubmission $submission) => $submission->submitted_at->toDateString())
            ->keyBy(fn (FormSubmission $submission) => $submission->submitted_at->toDateString());
    }

    public function getPeriodStart(): Jalalian
    {
        return $this->periodType === 'weekly'
            ? $this->jalaliAnchor()->getFirstDayOfWeek()
            : $this->jalaliAnchor()->getFirstDayOfMonth();
    }

    public function getPeriodEnd(): Jalalian
    {
        return $this->periodType === 'weekly'
            ? $this->jalaliAnchor()->getEndDayOfWeek()
            : $this->jalaliAnchor()->getEndDayOfMonth();
    }

    public function getSubmissionUrl(FormSubmission $submission): string
    {
        return FormSubmissionResource::getUrl('view', ['record' => $submission]);
    }

    private function jalaliAnchor(): Jalalian
    {
        return Jalalian::fromCarbon(Carbon::parse($this->anchorDate));
    }

    private function resolvedUserId(): int
    {
        if ($this->canSelectOtherUsers() && $this->userId) {
            return $this->userId;
        }

        return auth()->id();
    }
}
