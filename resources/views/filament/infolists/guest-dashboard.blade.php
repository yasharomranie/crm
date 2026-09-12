@php
    $guest = $record;
    $tier = $guest->loyaltyTier();
    $recency = $guest->recencyDays();
    $frequency = $guest->frequency();
    $monetary = $guest->monetaryTotal();
    $bookings = $guest->bookings()->with('room')->latest('check_in_date')->get();
    $openTickets = $guest->tickets()->whereNotIn('status', ['resolved', 'closed'])->count();
    $npsScores = $guest->feedbacks()->whereNotNull('nps_score')->pluck('nps_score');

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
    {{-- Header: identity + loyalty --}}
    <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border p-4">
        <div>
            <div class="text-lg font-semibold">{{ $guest->name }}</div>
            <div class="text-sm text-gray-500 dark:text-gray-400">
                {{ $guest->mobile ?? '—' }} @if($guest->national_code) · کد ملی {{ $guest->national_code }} @endif
            </div>
            @if ($guest->tags->isNotEmpty())
                <div class="mt-2 flex flex-wrap gap-1">
                    @foreach ($guest->tags as $tag)
                        <x-filament::badge color="gray">{{ $tag->name }}</x-filament::badge>
                    @endforeach
                </div>
            @endif
        </div>
        <div class="text-left">
            <x-filament::badge :color="$tier->getColor()" size="lg">
                سطح وفاداری: {{ $tier->getLabel() }}
            </x-filament::badge>
            <div class="mt-2 text-sm text-gray-500 dark:text-gray-400">موجودی کیف پول</div>
            <div class="text-lg font-semibold">{{ $toman((float) $guest->wallet_balance) }}</div>
        </div>
    </div>

    {{-- RFM + quick stats --}}
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <div class="rounded-lg border p-3">
            <div class="text-xs text-gray-500 dark:text-gray-400">آخرین اقامت (روز پیش)</div>
            <div class="text-lg font-semibold">{{ $recency ?? '—' }}</div>
        </div>
        <div class="rounded-lg border p-3">
            <div class="text-xs text-gray-500 dark:text-gray-400">تعداد اقامت تکمیل‌شده</div>
            <div class="text-lg font-semibold">{{ $frequency }}</div>
        </div>
        <div class="rounded-lg border p-3">
            <div class="text-xs text-gray-500 dark:text-gray-400">ارزش کل مشتری (CLV)</div>
            <div class="text-base font-semibold whitespace-nowrap">{{ $toman($monetary) }}</div>
        </div>
        <div class="rounded-lg border p-3 {{ $openTickets > 0 ? 'border-danger-300' : '' }}">
            <div class="text-xs text-gray-500 dark:text-gray-400">تیکت باز</div>
            <div class="text-lg font-semibold {{ $openTickets > 0 ? 'text-danger-600' : '' }}">{{ $openTickets }}</div>
        </div>
    </div>

    @if ($npsScores->isNotEmpty())
        <div class="text-sm text-gray-600 dark:text-gray-300">
            میانگین NPS ثبت‌شده: <span class="font-medium">{{ round($npsScores->avg(), 1) }}</span> از ۱۰ (بر اساس {{ $npsScores->count() }} نظرسنجی)
        </div>
    @endif

    {{-- Booking timeline --}}
    <div>
        <div class="mb-2 text-sm font-medium">تایم‌لاین رزروها</div>
        @if ($bookings->isEmpty())
            <div class="text-sm text-gray-400">هنوز رزروی ثبت نشده است.</div>
        @else
            <div class="space-y-2">
                @foreach ($bookings as $booking)
                    <div class="flex items-center justify-between rounded-lg border p-3 text-sm">
                        <div>
                            <span class="font-medium">{{ $booking->room->name }}</span>
                            <span class="text-gray-500 dark:text-gray-400">
                                ({{ $booking->check_in_date->format('Y/m/d') }} تا {{ $booking->check_out_date->format('Y/m/d') }})
                            </span>
                        </div>
                        <x-filament::badge :color="$booking->status->getColor()">
                            {{ $booking->status->getLabel() }}
                        </x-filament::badge>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
