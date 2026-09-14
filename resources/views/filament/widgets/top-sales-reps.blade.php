<x-filament-widgets::widget>
    <x-filament::section heading="برترین کارشناسان فروش" description="بر اساس ارزش معاملات بردهٔ هر کارشناس">
        @if ($reps->isEmpty())
            <div class="flex flex-col items-center gap-2 py-8 text-center">
                <x-filament::icon icon="heroicon-o-trophy" class="h-8 w-8 text-gray-300 dark:text-gray-600" />
                <p class="text-sm text-gray-500 dark:text-gray-400">هنوز معامله‌ای به سرانجام (برد) نرسیده است.</p>
            </div>
        @else
            <ul class="space-y-4">
                @foreach ($reps as $index => $rep)
                    <li class="flex items-center gap-3">
                        <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-xs font-bold
                            {{ $index === 0 ? 'bg-warning-100 text-warning-700 dark:bg-warning-500/20 dark:text-warning-400' : 'bg-gray-100 text-gray-500 dark:bg-white/5 dark:text-gray-400' }}">
                            {{ $index + 1 }}
                        </span>

                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary-100 text-sm font-semibold text-primary-700 dark:bg-primary-500/20 dark:text-primary-400">
                            {{ mb_substr($rep['user']?->name ?? '؟', 0, 1) }}
                        </span>

                        <div class="min-w-0 flex-1">
                            <div class="flex items-baseline justify-between gap-2">
                                <span class="truncate text-sm font-medium text-gray-950 dark:text-white">
                                    {{ $rep['user']?->name ?? 'بدون کارشناس' }}
                                </span>
                                <span class="shrink-0 text-xs text-gray-500 dark:text-gray-400">
                                    {{ $rep['won_count'] }} معامله
                                </span>
                            </div>

                            <div class="mt-1.5 h-1.5 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-white/10">
                                <div
                                    class="h-full rounded-full bg-primary-500"
                                    style="width: {{ min(100, (int) round($rep['won_value'] / $topValue * 100)) }}%"
                                ></div>
                            </div>

                            <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">
                                {{ number_format($rep['won_value']) }} تومان
                            </span>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
