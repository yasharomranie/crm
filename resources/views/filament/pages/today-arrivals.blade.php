<x-filament-panels::page>
    @php
        $toman = fn (int $amount): string => \Illuminate\Support\Number::toPersian(number_format($amount)).' تومان';
        $sections = [
            ['title' => 'ورود امروز', 'rows' => $this->getArrivals(), 'action' => 'checkIn', 'button' => 'چک‌این', 'empty' => 'امروز ورودی‌ای نیست.'],
            ['title' => 'خروج امروز (و تأخیر در خروج)', 'rows' => $this->getDepartures(), 'action' => 'checkOut', 'button' => 'چک‌اوت', 'empty' => 'مهمان مقیمی برای خروج امروز نیست.'],
        ];
    @endphp

    <div class="rounded-lg bg-gray-50 px-4 py-3 text-sm text-gray-700 dark:bg-white/5 dark:text-gray-300">
        الان {{ \Illuminate\Support\Number::toPersian($this->getInHouseCount()) }} رزرو مقیم است.
    </div>

    @foreach ($this->getDeskReminders() as $reminder)
        <a href="{{ $reminder['url'] }}" class="flex items-center justify-between rounded-lg border border-warning-200 bg-warning-50 px-4 py-3 text-sm text-warning-800 dark:border-warning-400/30 dark:bg-warning-400/10 dark:text-warning-300" wire:key="reminder-{{ $loop->index }}">
            <span>{{ $reminder['label'] }}</span>
            <span class="font-semibold">{{ \Illuminate\Support\Number::toPersian($reminder['count']) }}</span>
        </a>
    @endforeach

    @foreach ($sections as $section)
        <section class="space-y-3">
            <h3 class="text-base font-semibold text-gray-950 dark:text-white">
                {{ $section['title'] }} ({{ \Illuminate\Support\Number::toPersian($section['rows']->count()) }})
            </h3>

            @forelse ($section['rows'] as $booking)
                <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-900" wire:key="{{ $section['action'] }}-{{ $booking->id }}">
                    <div class="space-y-1">
                        <div class="font-medium text-gray-950 dark:text-white">
                            {{ $booking->guest->name }}
                            <span class="text-sm font-normal text-gray-500 dark:text-gray-400">· {{ $booking->room->name }}</span>
                        </div>
                        <div class="text-sm text-gray-500 dark:text-gray-400">
                            {{ $booking->check_in_date->toJalali('Y/m/d') }} تا {{ $booking->check_out_date->toJalali('Y/m/d') }}
                            · {{ \Illuminate\Support\Number::toPersian($booking->adults_count) }} نفر
                            @if ($booking->guest->mobile) · {{ \Illuminate\Support\Number::toPersian($booking->guest->mobile) }} @endif
                            @if ($booking->channel) · {{ $booking->channel->name }} @endif
                        </div>
                        @if ($booking->precheckin?->isCompleted())
                            <div class="text-sm text-success-700 dark:text-success-400">
                                پیش‌ثبت‌نام انجام شده
                                @if ($booking->precheckin->arrival_time) · رسیدن حدود {{ \Illuminate\Support\Number::toPersian($booking->precheckin->arrival_time) }} @endif
                                @if (count($booking->precheckin->companions ?? [])) · {{ \Illuminate\Support\Number::toPersian(count($booking->precheckin->companions)) }} همراه @endif
                            </div>
                            @if ($booking->precheckin->requests)
                                <div class="text-sm text-gray-600 dark:text-gray-300">درخواست مهمان: {{ $booking->precheckin->requests }}</div>
                            @endif
                        @elseif ($section['action'] === 'checkIn')
                            <div class="text-xs text-gray-400">پیش‌ثبت‌نام انجام نشده</div>
                        @endif
                        @if ($booking->notes)
                            <div class="text-sm text-gray-600 dark:text-gray-300">{{ $booking->notes }}</div>
                        @endif
                        @if ($section['action'] === 'checkOut' && $booking->check_out_date->lt(today()))
                            <div class="text-sm text-danger-600 dark:text-danger-400">خروج {{ $booking->check_out_date->toJalali('Y/m/d') }} بوده و هنوز ثبت نشده است.</div>
                        @endif
                    </div>

                    <div class="flex items-center gap-3">
                        @if ($booking->balanceDue() > 0)
                            <span class="whitespace-nowrap rounded-lg bg-warning-50 px-3 py-1 text-sm text-warning-700 dark:bg-warning-500/10 dark:text-warning-400">مانده: {{ $toman($booking->balanceDue()) }}</span>
                        @else
                            <span class="whitespace-nowrap rounded-lg bg-success-50 px-3 py-1 text-sm text-success-700 dark:bg-success-500/10 dark:text-success-400">تسویه</span>
                        @endif

                        @if ($booking->damageDeposit?->outstandingAmount())
                            <span class="whitespace-nowrap rounded-lg bg-info-50 px-3 py-1 text-sm text-info-700 dark:bg-info-500/10 dark:text-info-400">ودیعه: {{ $toman($booking->damageDeposit->outstandingAmount()) }}</span>
                        @endif

                        <x-filament::button size="sm" wire:click="{{ $section['action'] }}({{ $booking->id }})" wire:loading.attr="disabled">
                            {{ $section['button'] }}
                        </x-filament::button>
                    </div>
                </div>
            @empty
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ $section['empty'] }}</p>
            @endforelse
        </section>
    @endforeach
</x-filament-panels::page>
