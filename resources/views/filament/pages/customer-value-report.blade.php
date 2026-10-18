<x-filament-panels::page>
    @php($report = $this->report())
    @php($number = fn ($value) => \Illuminate\Support\Number::toPersian($value))

    <div dir="rtl" class="space-y-6">
        <p class="text-sm text-gray-500 dark:text-gray-400">
            فقط مشتریانی که دست‌کم یک اقامت تکمیل‌شده داشته‌اند. «ارزش» مجموع مبلغ صورت‌حساب اقامت‌های تکمیل‌شده است (سابقه‌ی واقعی، نه پیش‌بینی).
            «در معرض ریزش» یعنی فاصله‌ی فعلی از آخرین اقامت، بیش از ۱٫۵ برابر فاصله‌ی معمول خود همان مشتری بین اقامت‌هایش است (و حداقل ۶۰ روز)؛
            «از دست رفته» یعنی یک سال یا بیشتر بدون اقامت.
        </p>

        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <div class="rounded-lg border p-3"><div class="text-xs text-gray-500">مشتریان دارای اقامت</div><div class="text-lg font-semibold">{{ $number($report['customers']) }}</div></div>
            <div class="rounded-lg border p-3"><div class="text-xs text-gray-500">درآمد کل ثبت‌شده</div><div class="text-lg font-semibold">{{ $this->toman($report['revenue']) }}</div></div>
            <div class="rounded-lg border p-3"><div class="text-xs text-gray-500">میانگین ارزش هر مشتری</div><div class="text-lg font-semibold">{{ $this->toman($report['average_value']) }}</div></div>
            <div class="rounded-lg border p-3"><div class="text-xs text-gray-500">نرخ بازگشت (بیش از یک اقامت)</div><div class="text-lg font-semibold">{{ $report['repeat_rate_percent'] === null ? '—' : $number($report['repeat_rate_percent']).'٪' }}</div></div>
            <div class="rounded-lg border p-3"><div class="text-xs text-gray-500">فعال</div><div class="text-lg font-semibold">{{ $number($report['active']) }}</div></div>
            <div class="rounded-lg border p-3 {{ $report['at_risk'] > 0 ? 'border-warning-500' : '' }}"><div class="text-xs text-gray-500">در معرض ریزش</div><div class="text-lg font-semibold">{{ $number($report['at_risk']) }}</div></div>
            <div class="rounded-lg border p-3"><div class="text-xs text-gray-500">از دست رفته</div><div class="text-lg font-semibold">{{ $number($report['lost']) }}</div></div>
        </div>

        <div class="grid gap-4 lg:grid-cols-3">
            <div class="rounded-lg border p-3">
                <h3 class="mb-2 text-sm font-semibold">سهم هر سطح وفاداری</h3>
                @forelse ($report['tiers'] as $row)
                    <div class="flex justify-between gap-2 text-sm"><span>{{ $row['tier'] }} ({{ $number($row['guests']) }} نفر)</span><span>{{ $this->toman($row['revenue']) }}</span></div>
                @empty
                    <p class="text-sm text-gray-500">داده‌ای نیست.</p>
                @endforelse
            </div>

            <div class="rounded-lg border p-3 lg:col-span-1">
                <h3 class="mb-2 text-sm font-semibold">ارزشمندترین مشتریان</h3>
                @forelse ($report['top_guests'] as $row)
                    <div class="flex justify-between gap-2 text-sm">
                        <a class="text-primary-600 hover:underline" href="{{ \App\Filament\Resources\Guests\GuestResource::getUrl('view', ['record' => $row['guest']]) }}">{{ $row['guest']->name }}</a>
                        <span>{{ $this->toman($row['spend']) }}</span>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">داده‌ای نیست.</p>
                @endforelse
            </div>

            <div class="rounded-lg border p-3">
                <h3 class="mb-2 text-sm font-semibold">ارزشمندان در معرض ریزش</h3>
                @forelse ($report['at_risk_guests'] as $row)
                    <div class="text-sm">
                        <a class="text-primary-600 hover:underline" href="{{ \App\Filament\Resources\Guests\GuestResource::getUrl('view', ['record' => $row['guest']]) }}">{{ $row['guest']->name }}</a>
                        <span class="text-xs text-gray-500">· {{ $number($row['days_since_last']) }} روز از آخرین اقامت{{ $row['usual_gap_days'] ? ' (معمولاً هر '.$number($row['usual_gap_days']).' روز)' : '' }} · {{ $this->toman($row['spend']) }}</span>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">مشتری در معرض ریزشی نیست.</p>
                @endforelse
            </div>
        </div>
    </div>
</x-filament-panels::page>
