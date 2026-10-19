<x-filament-panels::page>
    @php($report = $this->report())
    @php($number = fn ($value) => $value === null ? '—' : \Illuminate\Support\Number::toPersian((string) $value))

    <div dir="rtl" class="space-y-6">
        <div class="flex flex-wrap items-center gap-3">
            <label for="hotel-report-period" class="text-sm text-gray-600 dark:text-gray-300">بازه‌ی گزارش</label>
            <select id="hotel-report-period" wire:model.live="period" class="rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-gray-900">
                @foreach (\App\Filament\Pages\HotelReports::PERIODS as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>
            <span class="text-sm text-gray-500 dark:text-gray-400">
                {{ $report['from']->toJalali('Y/m/d') }} تا {{ $report['to']->toJalali('Y/m/d') }} · {{ $number($report['rooms']) }} واحد فعال
            </span>
        </div>

        <p class="text-sm text-gray-500 dark:text-gray-400">
            فقط رزروهای «قطعی»، «چک‌این‌شده» و «چک‌اوت‌شده» فروش حساب می‌شوند. درآمد هر رزرو (کل صورت‌حساب پس از تخفیف) به‌طور مساوی بین شب‌هایش پخش می‌شود و فقط شب‌های داخل بازه شمرده می‌شود.
            «تعداد شب‌اتاق در دسترس» = واحدهای فعال × شب‌های بازه.
        </p>

        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <div class="rounded-lg border p-3"><div class="text-xs text-gray-500">درصد اشغال</div><div class="text-lg font-semibold">{{ $this->percent($report['occupancy_percent']) }}</div><div class="text-xs text-gray-500">{{ $number($report['room_nights_sold']) }} از {{ $number($report['room_nights_available']) }} شب‌اتاق</div></div>
            <div class="rounded-lg border p-3"><div class="text-xs text-gray-500">میانگین نرخ فروش (ADR)</div><div class="text-lg font-semibold">{{ $this->toman($report['adr']) }}</div><div class="text-xs text-gray-500">درآمد به ازای هر شب‌اتاق فروخته‌شده</div></div>
            <div class="rounded-lg border p-3"><div class="text-xs text-gray-500">درآمد به ازای هر اتاق در دسترس (RevPAR)</div><div class="text-lg font-semibold">{{ $this->toman($report['revpar']) }}</div><div class="text-xs text-gray-500">اشغال × ADR</div></div>
            <div class="rounded-lg border p-3"><div class="text-xs text-gray-500">درآمد اقامت</div><div class="text-lg font-semibold">{{ $this->toman($report['revenue']) }}</div><div class="text-xs text-gray-500">{{ $number($report['bookings']) }} رزرو · میانگین اقامت {{ $number($report['average_stay_nights']) }} شب</div></div>
            <div class="rounded-lg border p-3"><div class="text-xs text-gray-500">لغو</div><div class="text-lg font-semibold">{{ $number($report['cancellations']) }}</div><div class="text-xs text-gray-500">نرخ لغو {{ $this->percent($report['cancellation_rate_percent']) }}</div></div>
            <div class="rounded-lg border p-3"><div class="text-xs text-gray-500">عدم حضور</div><div class="text-lg font-semibold">{{ $number($report['no_shows']) }}</div></div>
            <div class="rounded-lg border p-3"><div class="text-xs text-gray-500">جریمه‌ی لغو</div><div class="text-lg font-semibold">{{ $this->toman($report['cancellation_fees']) }}</div><div class="text-xs text-gray-500">جدا از درآمد اقامت</div></div>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            @foreach ([['title' => 'به تفکیک کانال فروش', 'rows' => $report['by_channel']], ['title' => 'به تفکیک منبع رزرو', 'rows' => $report['by_source']]] as $table)
                <div class="rounded-lg border p-3">
                    <h3 class="mb-2 text-sm font-semibold">{{ $table['title'] }}</h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead><tr class="text-right text-xs text-gray-500"><th class="py-1">نام</th><th>رزرو</th><th>شب‌اتاق</th><th>درآمد</th><th>سهم</th><th>ADR</th></tr></thead>
                            <tbody>
                                @forelse ($table['rows'] as $row)
                                    <tr class="border-t">
                                        <td class="py-1">{{ $row['name'] }}</td>
                                        <td>{{ $number($row['bookings']) }}</td>
                                        <td>{{ $number($row['room_nights']) }}</td>
                                        <td class="whitespace-nowrap">{{ $this->toman($row['revenue']) }}</td>
                                        <td>{{ $this->percent($row['share_percent']) }}</td>
                                        <td class="whitespace-nowrap">{{ $this->toman($row['adr']) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="py-2 text-gray-500">در این بازه فروشی ثبت نشده است.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="rounded-lg border p-3">
            <h3 class="mb-1 text-sm font-semibold">پیش‌بینی ۳۰ روز آینده (شب‌های رزروشده)</h3>
            <p class="mb-3 text-xs text-gray-500">ستون پرِ هر روز = رزروهای قطعی؛ بخش کم‌رنگ = رزروهای «در انتظار» که هنوز قطعی نشده‌اند.</p>
            <div class="flex items-end gap-1 overflow-x-auto pb-1" style="min-height: 7rem">
                @foreach ($report['forecast'] as $day)
                    @php
                        $capacity = max(1, $day['available']);
                        $firm = min(100, $day['sold'] / $capacity * 100);
                        $soft = min(100 - $firm, $day['pending'] / $capacity * 100);
                    @endphp
                    <div class="flex w-6 shrink-0 flex-col items-center gap-1" title="{{ \Illuminate\Support\Carbon::parse($day['date'])->toJalali('Y/m/d') }}: {{ $day['sold'] }} قطعی، {{ $day['pending'] }} در انتظار از {{ $day['available'] }} واحد">
                        <div class="flex h-20 w-full flex-col justify-end overflow-hidden rounded bg-gray-100 dark:bg-white/10">
                            <div class="w-full bg-primary-300/60" style="height: {{ $soft }}%"></div>
                            <div class="w-full bg-primary-600" style="height: {{ $firm }}%"></div>
                        </div>
                        <span class="text-[10px] text-gray-500">{{ $number(\Illuminate\Support\Carbon::parse($day['date'])->toJalali('d')) }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</x-filament-panels::page>
