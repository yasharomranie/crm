<x-filament-panels::page>
    @php
        $report = $this->report();
        $number = fn ($value) => $value === null ? '—' : \Illuminate\Support\Number::toPersian((string) $value);
        $sections = [
            'never_arrived' => ['title' => 'مهمانی که نیامده (ورودشان گذشته)', 'hint' => 'یا هنوز می‌آیند و چک‌این می‌شوند، یا «عدم حضور» را ثبت کنید تا اتاق آزاد شود.'],
            'overdue_departures' => ['title' => 'مهمانی که باید می‌رفتند و هنوز مقیم‌اند', 'hint' => 'چک‌اوتشان را بزنید یا اقامتشان را تمدید کنید.'],
            'departing_today' => ['title' => 'خروج امروز که هنوز چک‌اوت نشده', 'hint' => 'تا آخر روز چک‌اوت کنید.'],
            'unpaid' => ['title' => 'رزرو مقیم یا تازه‌خارج‌شده با مانده', 'hint' => 'پرداخت را بگیرید (یا لینک پرداخت بفرستید).'],
            'unsettled_deposits' => ['title' => 'ودیعه‌ی تسویه‌نشده', 'hint' => 'ودیعه را برگردانید یا خسارت را کسر کنید.'],
        ];
    @endphp

    <div dir="rtl" class="space-y-6">
        <div class="flex flex-wrap items-center gap-3">
            <x-filament::button size="sm" color="gray" wire:click="shiftDay(-1)">روز قبل</x-filament::button>
            <span class="font-semibold">{{ $report['date']->toJalali('Y/m/d') }}</span>
            <x-filament::button size="sm" color="gray" wire:click="shiftDay(1)" :disabled="$report['date']->isToday()">روز بعد</x-filament::button>

            @if ($report['closing'])
                <span class="rounded-lg bg-success-50 px-3 py-1 text-sm text-success-700 dark:bg-success-500/10 dark:text-success-400">
                    بسته شده در {{ $report['closing']->closed_at->toJalali('Y/m/d H:i') }}{{ $report['closing']->closer ? ' توسط '.$report['closing']->closer->name : '' }}
                </span>
            @else
                <x-filament::button size="sm" color="success" wire:click="closeDay" wire:confirm="روز بسته شود؟ {{ $number($report['issue_count']) }} مورد باز در سابقه ثبت می‌شود.">بستن روز</x-filament::button>
            @endif
        </div>

        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <div class="rounded-lg border p-3"><div class="text-xs text-gray-500">مقیم الان</div><div class="text-lg font-semibold">{{ $number($report['in_house']) }} از {{ $number($report['rooms']) }}</div><div class="text-xs text-gray-500">{{ $report['occupancy_percent'] === null ? '—' : $number($report['occupancy_percent']).'٪ اشغال' }}</div></div>
            <div class="rounded-lg border p-3"><div class="text-xs text-gray-500">گردش پول این روز</div><div class="text-lg font-semibold">{{ $this->money($report['cash']['total']) }}</div><div class="text-xs text-gray-500">پرداخت و پیش‌پرداخت منهای استرداد</div></div>
            <div class="rounded-lg border p-3 {{ $report['issue_count'] > 0 ? 'border-warning-500' : '' }}"><div class="text-xs text-gray-500">موارد باز</div><div class="text-lg font-semibold">{{ $number($report['issue_count']) }}</div></div>
        </div>

        <div class="rounded-lg border p-3">
            <h3 class="mb-2 text-sm font-semibold">پول دریافتی به تفکیک روش</h3>
            @forelse ($report['cash']['by_method'] as $method => $amount)
                <div class="flex justify-between text-sm"><span>{{ $this->methodLabel($method) }}</span><span>{{ $this->money($amount) }}</span></div>
            @empty
                <p class="text-sm text-gray-500">در این روز پولی ثبت نشده است.</p>
            @endforelse
        </div>

        @foreach ($sections as $key => $section)
            @if ($report['issues'][$key]->isNotEmpty())
                <section class="space-y-2" wire:key="issues-{{ $key }}">
                    <h3 class="text-base font-semibold">{{ $section['title'] }} ({{ $number($report['issues'][$key]->count()) }})</h3>
                    <p class="text-xs text-gray-500">{{ $section['hint'] }}</p>
                    @foreach ($report['issues'][$key] as $booking)
                        <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border p-3 text-sm" wire:key="{{ $key }}-{{ $booking->id }}">
                            <div>
                                <a class="font-medium text-primary-600 hover:underline" href="{{ \App\Filament\Resources\Bookings\BookingResource::getUrl('edit', ['record' => $booking]) }}">{{ $booking->guest->name }}</a>
                                · {{ $booking->room->name }} · {{ $booking->reference_code }}
                                <div class="text-xs text-gray-500">{{ $booking->check_in_date->toJalali('Y/m/d') }} تا {{ $booking->check_out_date->toJalali('Y/m/d') }} · {{ $this->statusLabel($booking) }}</div>
                            </div>
                            <div class="flex items-center gap-3">
                                @if (in_array($key, ['unpaid']))
                                    <span class="whitespace-nowrap">مانده: {{ $this->money($booking->balanceDue()) }}</span>
                                @endif
                                @if ($key === 'unsettled_deposits')
                                    <span class="whitespace-nowrap">ودیعه: {{ $this->money($booking->damageDeposit->outstandingAmount()) }}</span>
                                @endif
                                @if ($key === 'never_arrived')
                                    <x-filament::button size="xs" color="gray" wire:click="markNoShow({{ $booking->id }})" wire:confirm="عدم حضور ثبت شود؟ جریمه طبق سیاست لغو همین رزرو حساب می‌شود.">عدم حضور</x-filament::button>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </section>
            @endif
        @endforeach

        @if ($report['issues']['room_mismatches']->isNotEmpty())
            <section class="space-y-2">
                <h3 class="text-base font-semibold">اتاق با وضعیت نامطابق ({{ $number($report['issues']['room_mismatches']->count()) }})</h3>
                <p class="text-xs text-gray-500">وضعیت را در «رک اتاق» یا ویرایش اتاق اصلاح کنید.</p>
                @foreach ($report['issues']['room_mismatches'] as $row)
                    <div class="rounded-lg border p-3 text-sm">{{ $row['room']->name }} - {{ $row['problem'] }}</div>
                @endforeach
            </section>
        @endif

        @if ($report['issue_count'] === 0)
            <p class="text-sm text-success-700 dark:text-success-400">همه‌چیز مرتب است؛ موردی باز نمانده.</p>
        @endif
    </div>
</x-filament-panels::page>
