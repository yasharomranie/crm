<x-filament-panels::page>
    {{-- dir="ltr" on the scroll container itself, not just its contents -
    otherwise, being inside an RTL page, its scrollLeft:0 starts at the
    *last* pipeline stage instead of "سرنخ جدید". --}}
    <div dir="ltr" class="overflow-x-auto">
        <div class="flex gap-4 pb-4" style="min-width: max-content;">
            @foreach ($this->getColumns() as $stageValue => $deals)
                @php $stage = \App\Enums\DealStage::from($stageValue); @endphp
                <div dir="rtl" class="w-72 shrink-0 rounded-lg border bg-gray-50 dark:bg-gray-900/40">
                    <div class="flex items-center justify-between border-b p-3">
                        <span class="text-sm font-medium">{{ $stage->getLabel() }}</span>
                        <x-filament::badge :color="$stage->getColor()">{{ $deals->count() }}</x-filament::badge>
                    </div>
                    <div class="space-y-2 p-2">
                        @forelse ($deals as $deal)
                            <div class="rounded-lg border bg-white p-3 text-sm shadow-sm dark:bg-gray-800">
                                <div class="font-medium">{{ $deal->title }}</div>
                                <div class="text-gray-500 dark:text-gray-400">{{ $deal->guest?->name ?? 'بدون مشتری' }}</div>
                                @if ($deal->value)
                                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        {{ number_format($deal->value) }} تومان
                                    </div>
                                @endif
                                @if ($deal->assignee)
                                    <div class="mt-1 text-xs text-gray-400">کارشناس: {{ $deal->assignee->name }}</div>
                                @endif
                                <div class="mt-2 flex justify-between">
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
                                        class="text-xs text-primary-600 hover:text-primary-700"
                                    >
                                        مرحله بعد
                                    </button>
                                </div>
                            </div>
                        @empty
                            <div class="p-2 text-center text-xs text-gray-400">خالی</div>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</x-filament-panels::page>
