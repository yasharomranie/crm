@php
    $project = $record;
    $ev = $project->earnedValue();
    $pv = $project->plannedValue();
    $ac = $project->actualCost();
    $cv = $project->costVariance();
    $sv = $project->scheduleVariance();
    $evPercent = $project->earnedValuePercent();
    $pvPercent = $project->plannedValuePercent();
    $totalWeight = $project->totalLeafWeight();
    $criticalIds = $project->criticalPathPhaseIds();
    $phases = $project->phases()->orderBy('sort_order')->get();

    $rangeStart = $project->start_date;
    $rangeEnd = $project->end_date;
    $rangeDays = ($rangeStart && $rangeEnd) ? max(1, $rangeStart->diffInDays($rangeEnd)) : null;

    $typeColors = [
        'phase' => 'bg-primary-200 dark:bg-primary-800',
        'activity' => 'bg-gray-300 dark:bg-gray-600',
        'milestone' => 'bg-warning-400 dark:bg-warning-600',
    ];

    // Large Toman amounts read far better (and fit small cards) as
    // "X.X میلیون/میلیارد تومان" than as raw 9-10 digit numbers.
    $toman = function (float $amount): string {
        $sign = $amount < 0 ? '-' : '';
        $amount = abs($amount);

        return match (true) {
            $amount >= 1_000_000_000 => $sign.number_format($amount / 1_000_000_000, 1).' میلیارد تومان',
            $amount >= 1_000_000 => $sign.number_format($amount / 1_000_000, 1).' میلیون تومان',
            default => $sign.number_format($amount).' تومان',
        };
    };
@endphp

<div class="space-y-6">
    {{-- EVM stat cards --}}
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
        <div class="rounded-lg border p-3">
            <div class="text-xs text-gray-500 dark:text-gray-400">پیشرفت فیزیکی</div>
            <div class="text-lg font-semibold">{{ round($evPercent) }}٪</div>
        </div>
        <div class="rounded-lg border p-3">
            <div class="text-xs text-gray-500 dark:text-gray-400">پیشرفت برنامه‌ای</div>
            <div class="text-lg font-semibold">{{ round($pvPercent) }}٪</div>
        </div>
        <div class="rounded-lg border p-3">
            <div class="text-xs text-gray-500 dark:text-gray-400">ارزش کسب‌شده (EV)</div>
            <div class="text-base font-semibold whitespace-nowrap">{{ $toman($ev) }}</div>
        </div>
        <div class="rounded-lg border p-3">
            <div class="text-xs text-gray-500 dark:text-gray-400">هزینه واقعی (AC)</div>
            <div class="text-base font-semibold whitespace-nowrap">{{ $toman($ac) }}</div>
        </div>
        <div class="rounded-lg border p-3 {{ $cv >= 0 ? 'border-success-300' : 'border-danger-300' }}">
            <div class="text-xs text-gray-500 dark:text-gray-400">انحراف بودجه (CV)</div>
            <div class="text-base font-semibold whitespace-nowrap {{ $cv >= 0 ? 'text-success-600' : 'text-danger-600' }}">
                {{ $cv >= 0 ? '+' : '' }}{{ $toman($cv) }}
            </div>
        </div>
        <div class="rounded-lg border p-3 {{ $sv >= 0 ? 'border-success-300' : 'border-danger-300' }}">
            <div class="text-xs text-gray-500 dark:text-gray-400">انحراف زمان (SV)</div>
            <div class="text-base font-semibold whitespace-nowrap {{ $sv >= 0 ? 'text-success-600' : 'text-danger-600' }}">
                {{ $sv >= 0 ? '+' : '' }}{{ $toman($sv) }}
            </div>
        </div>
    </div>

    @if ($totalWeight != 100)
        <div class="rounded-lg border border-warning-300 bg-warning-50 dark:bg-warning-950/40 p-3 text-sm">
            ⚠ مجموع وزن آیتم‌های برگ WBS برابر {{ $totalWeight }}٪ است (باید ۱۰۰٪ باشد) تا اعداد EVM دقیق باشند.
        </div>
    @endif

    {{-- Gantt-like timeline --}}
    <div>
        <div class="mb-2 text-sm font-medium">گانت‌چارت (ساده‌شده)</div>

        @if (! $rangeStart || ! $rangeEnd)
            <div class="text-sm text-gray-400">برای نمایش گانت، تاریخ شروع و پایان پروژه را ثبت کنید.</div>
        @else
            <div dir="ltr" class="space-y-1.5 rounded-lg border p-3">
                @foreach ($phases as $phase)
                    @php
                        $isCritical = in_array($phase->id, $criticalIds);
                        $hasDates = $phase->start_date && $phase->end_date;
                        $offsetPercent = $hasDates ? max(0, min(100, ($rangeStart->diffInDays($phase->start_date) / $rangeDays) * 100)) : 0;
                        $widthPercent = $hasDates ? max(2, min(100 - $offsetPercent, ($phase->start_date->diffInDays($phase->end_date) / $rangeDays) * 100)) : 0;
                        $indent = $phase->parent_id ? 1 : 0;
                    @endphp
                    <div class="flex items-center gap-3">
                        <div class="w-48 shrink-0 truncate text-sm {{ $indent ? 'ps-4 text-gray-600 dark:text-gray-300' : 'font-medium' }}" style="padding-inline-start: {{ $indent * 16 }}px" dir="rtl">
                            {{ $phase->title }}
                            @if ($isCritical)
                                <span class="text-danger-500" title="روی مسیر بحرانی">★</span>
                            @endif
                        </div>
                        <div class="relative h-5 flex-1 rounded bg-gray-100 dark:bg-gray-800">
                            @if ($hasDates)
                                <div
                                    class="absolute top-0 h-5 rounded {{ $typeColors[$phase->type->value] ?? 'bg-gray-300' }} {{ $isCritical ? 'ring-2 ring-danger-500' : '' }}"
                                    style="left: {{ $offsetPercent }}%; width: {{ $widthPercent }}%;"
                                    title="{{ $phase->start_date->toJalali('Y/m/d') }} - {{ $phase->end_date->toJalali('Y/m/d') }}"
                                >
                                    <div class="h-full rounded bg-success-500/70" style="width: {{ $phase->progress_percent }}%"></div>
                                </div>
                            @endif
                        </div>
                        <div class="w-10 shrink-0 text-xs text-gray-500">{{ $phase->progress_percent }}٪</div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    @if (count($criticalIds))
        <div class="text-sm">
            <span class="font-medium">مسیر بحرانی (ساده‌شده):</span>
            {{ $phases->whereIn('id', $criticalIds)->sortBy(fn ($p) => array_search($p->id, $criticalIds))->pluck('title')->implode(' ← ') }}
        </div>
    @endif
</div>
