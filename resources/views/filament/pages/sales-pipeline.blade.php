<x-filament-panels::page>
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-base font-semibold text-gray-950 dark:text-white">نمای کانبان</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">معامله را بین مراحل جابه‌جا کنید تا وضعیت پایپ‌لاین به‌روز بماند.</p>
        </div>
    </div>

    {{-- dir="ltr" on the scroll container itself, not just its contents -
    otherwise, being inside an RTL page, its scrollLeft:0 starts at the
    *last* pipeline stage instead of "سرنخ جدید". --}}
    <div dir="ltr" class="mt-3 overflow-x-auto">
        <div class="flex gap-4 pb-4" style="min-width: max-content;">
            @foreach ($this->getColumns() as $stageValue => $deals)
                @php
                    $stage = \App\Enums\DealStage::from($stageValue);
                    $stageValueSum = $deals->sum('value');
                @endphp
                <div dir="rtl" class="flex w-80 shrink-0 flex-col rounded-xl border border-gray-200 bg-gray-50/70 dark:border-white/10 dark:bg-white/[0.02]">
                    <div class="flex items-center justify-between gap-2 border-b border-gray-200 p-3 dark:border-white/10">
                        <div class="flex items-center gap-2">
                            <span class="h-2 w-2 shrink-0 rounded-full" style="background-color: {{ ['gray' => '#9ca3af', 'info' => '#3b82f6', 'warning' => '#f59e0b', 'success' => '#16a34a', 'danger' => '#dc2626'][$stage->getColor()] }}"></span>
                            <span class="text-sm font-semibold text-gray-950 dark:text-white">{{ $stage->getLabel() }}</span>
                        </div>
                        <x-filament::badge :color="$stage->getColor()">{{ $deals->count() }}</x-filament::badge>
                    </div>

                    @if ($stageValueSum > 0)
                        <div class="border-b border-gray-200 px-3 py-1.5 text-xs text-gray-500 dark:border-white/10 dark:text-gray-400">
                            جمع ارزش: <span class="font-medium text-gray-700 dark:text-gray-300">{{ number_format($stageValueSum) }} تومان</span>
                        </div>
                    @endif

                    <div class="flex-1 space-y-2 p-2">
                        @forelse ($deals as $deal)
                            @php $daysInStage = (int) $deal->updated_at->diffInDays(now()); @endphp
                            <div
                                class="rounded-lg border border-gray-200 border-r-4 bg-white p-3 text-sm shadow-sm transition hover:shadow-md dark:border-white/10 dark:bg-gray-800"
                                style="border-right-color: {{ ['gray' => '#9ca3af', 'info' => '#3b82f6', 'warning' => '#f59e0b', 'success' => '#16a34a', 'danger' => '#dc2626'][$stage->getColor()] }};"
                            >
                                <div class="flex items-start justify-between gap-2">
                                    <span class="font-medium text-gray-950 dark:text-white">{{ $deal->title }}</span>
                                    @if ($deal->source)
                                        <x-filament::badge color="gray" size="xs">{{ $deal->source->getLabel() }}</x-filament::badge>
                                    @endif
                                </div>

                                <div class="mt-2 flex items-center gap-1.5 text-gray-500 dark:text-gray-400">
                                    <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-gray-100 text-[10px] font-semibold text-gray-600 dark:bg-white/10 dark:text-gray-300">
                                        {{ mb_substr($deal->guest?->name ?? '؟', 0, 1) }}
                                    </span>
                                    <span class="truncate">{{ $deal->guest?->name ?? 'بدون مشتری' }}</span>
                                </div>

                                @if ($deal->value)
                                    <div class="mt-1.5 text-xs font-medium text-gray-700 dark:text-gray-300">
                                        {{ number_format($deal->value) }} تومان
                                    </div>
                                @endif

                                @if ($deal->assignee)
                                    <div class="mt-1.5 flex items-center gap-1.5 text-xs text-gray-400">
                                        <span class="flex h-4 w-4 shrink-0 items-center justify-center rounded-full bg-primary-100 text-[9px] font-semibold text-primary-700 dark:bg-primary-500/20 dark:text-primary-400">
                                            {{ mb_substr($deal->assignee->name, 0, 1) }}
                                        </span>
                                        <span class="truncate">{{ $deal->assignee->name }}</span>
                                    </div>
                                @endif

                                <div class="mt-2 flex items-center justify-between border-t border-gray-100 pt-2 dark:border-white/5">
                                    <span
                                        @class([
                                            'text-[11px]',
                                            'text-gray-400 dark:text-gray-500' => $daysInStage < 14,
                                            'font-medium text-danger-600 dark:text-danger-400' => $daysInStage >= 14,
                                        ])
                                    >
                                        {{ $daysInStage }} روز در این مرحله
                                    </span>

                                    <div class="flex gap-2">
                                        <button
                                            type="button"
                                            wire:click="moveStage({{ $deal->id }}, 'prev')"
                                            class="text-xs text-gray-400 hover:text-gray-700 dark:hover:text-gray-200"
                                        >
                                            مرحله قبل
                                        </button>
                                        <button
                                            type="button"
                                            wire:click="moveStage({{ $deal->id }}, 'next')"
                                            class="text-xs font-medium text-primary-600 hover:text-primary-700"
                                        >
                                            مرحله بعد
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="flex flex-col items-center gap-1.5 py-8 text-center text-gray-400 dark:text-gray-600">
                                <x-filament::icon icon="heroicon-o-inbox" class="h-6 w-6" />
                                <span class="text-xs">معامله‌ای در این مرحله نیست</span>
                            </div>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</x-filament-panels::page>
