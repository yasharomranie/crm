<x-filament-panels::page>
    @php
        $toman = function (float $amount): string {
            $sign = $amount < 0 ? '-' : '';
            $amount = abs($amount);

            $formatted = match (true) {
                $amount >= 1_000_000_000 => $sign.number_format($amount / 1_000_000_000, 1).' میلیارد تومان',
                $amount >= 1_000_000 => $sign.number_format($amount / 1_000_000, 1).' میلیون تومان',
                default => $sign.number_format($amount).' تومان',
            };

            return \Illuminate\Support\Number::toPersian($formatted);
        };
    @endphp

    <div class="space-y-6">
        @forelse ($this->getChannels() as $channel)
            <div class="fi-section rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900 sm:p-6">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h3 class="text-base font-semibold text-gray-950 dark:text-white">
                            {{ $channel->name }}
                        </h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            {{ $channel->type->getLabel() }} · کمیسیون {{ \Illuminate\Support\Number::toPersian($channel->commission_rate) }}٪
                        </p>
                    </div>

                    <div class="flex gap-3">
                        <div class="rounded-lg bg-gray-50 px-4 py-2 text-center dark:bg-white/5">
                            <div class="text-lg font-bold text-gray-950 dark:text-white">
                                {{ \Illuminate\Support\Number::toPersian($channel->bookings_count_cache) }}
                            </div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">رزرو فعال</div>
                        </div>
                        <div class="rounded-lg bg-warning-50 px-4 py-2 text-center dark:bg-warning-500/10">
                            <div class="text-lg font-bold text-warning-600 dark:text-warning-400 whitespace-nowrap">
                                {{ $toman($channel->commission_owed_cache) }}
                            </div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">کمیسیون تجمیعی</div>
                        </div>
                    </div>
                </div>

                @if ($channel->allotmentUsageCache->isNotEmpty())
                    <div class="mt-4 space-y-2">
                        @foreach ($channel->allotmentUsageCache as $usage)
                            @php
                                $percent = $usage['allotment_count'] > 0
                                    ? min(100, (int) round($usage['used_count'] / $usage['allotment_count'] * 100))
                                    : 0;
                                $barColor = $percent >= 100 ? 'bg-danger-500' : ($percent >= 70 ? 'bg-warning-500' : 'bg-success-500');
                            @endphp
                            <div>
                                <div class="flex items-center justify-between text-sm text-gray-700 dark:text-gray-300">
                                    <span>{{ $usage['room_type'] }}</span>
                                    <span class="whitespace-nowrap">{{ \Illuminate\Support\Number::toPersian($usage['used_count']) }} از {{ \Illuminate\Support\Number::toPersian($usage['allotment_count']) }} سهمیه (امروز)</span>
                                </div>
                                <div class="mt-1 h-2 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-white/10">
                                    <div class="h-full {{ $barColor }}" style="width: {{ $percent }}%"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">
                        برای این کانال هنوز سهمیه‌ای در بخش «سهمیه‌بندی کانال‌ها» تعریف نشده است.
                    </p>
                @endif
            </div>
        @empty
            <div class="rounded-xl border border-dashed border-gray-300 p-8 text-center text-gray-500 dark:border-white/10 dark:text-gray-400">
                هنوز کانال فروشی ثبت نشده است.
            </div>
        @endforelse
    </div>
</x-filament-panels::page>
