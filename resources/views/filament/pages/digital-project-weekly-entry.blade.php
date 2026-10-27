<x-filament-panels::page>
    @php
        $end = $this->weekEndDate();
        $start = $end->copy()->subDays(6);
        $jalali = fn ($date) => \Illuminate\Support\Number::toPersian(\Morilog\Jalali\Jalalian::fromCarbon($date)->format('Y/m/d'));
    @endphp

    <div dir="rtl" class="space-y-4">
        <div class="flex flex-wrap items-center gap-3">
            <x-filament::button size="sm" color="gray" wire:click="shiftWeek(-1)">هفتهٔ قبل</x-filament::button>
            <span class="font-semibold">هفتهٔ {{ $jalali($start) }} تا {{ $jalali($end) }}</span>
            <x-filament::button size="sm" color="gray" wire:click="shiftWeek(1)">هفتهٔ بعد</x-filament::button>
            <x-filament::button size="sm" color="gray" tag="a" :href="$this->reportUrl()" target="_blank">گزارش مدیریتی (A4 افقی)</x-filament::button>
        </div>

        <p class="text-sm text-gray-500 dark:text-gray-400">
            برای هر پروژه عددهای همین هفته را بنویسید؛ خانهٔ خالی یعنی «ثبت نشده». «فالور» تعداد فعلی صفحه است و رشد هفته از اختلافش با هفتهٔ قبل در گزارش حساب می‌شود.
        </p>

        <form wire:submit="save" class="space-y-4">
            <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-white/10">
                <table class="w-full min-w-[40rem] text-sm">
                    <thead class="bg-gray-50 dark:bg-white/5">
                        <tr>
                            <th class="px-3 py-2 text-right font-semibold">پروژه</th>
                            @foreach ($this->metrics() as $metric)
                                <th class="px-2 py-2 text-center font-semibold">{{ $metric === \App\Enums\DigitalProjectMetric::Followers ? 'فالور (تعداد الان)' : $metric->getLabel() }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    @foreach ($this->groups() as $group)
                        <tbody wire:key="entry-group-{{ $group['channel']->value }}">
                            <tr><th colspan="{{ 1 + count($this->metrics()) }}" class="bg-gray-100 px-3 py-1 text-right text-xs font-semibold dark:bg-white/10">{{ $group['channel']->getLabel() }}</th></tr>
                            @foreach ($group['projects'] as $project)
                                <tr wire:key="entry-project-{{ $project->id }}" class="border-t border-gray-100 dark:border-white/5">
                                    <td class="px-3 py-1">{{ $project->name }}</td>
                                    @foreach ($this->metrics() as $metric)
                                        <td class="px-1 py-1">
                                            <input
                                                type="number"
                                                min="0"
                                                inputmode="numeric"
                                                wire:model="values.{{ $project->id }}.{{ $metric->value }}"
                                                aria-label="{{ $project->name }} — {{ $metric->getLabel() }}"
                                                class="w-full rounded-md border border-gray-300 bg-white px-2 py-1 text-center text-sm dark:border-white/20 dark:bg-white/5"
                                            >
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    @endforeach
                </table>
            </div>

            @error('values.*.*') <p class="text-sm text-danger-600">{{ $message }}</p> @enderror

            <x-filament::button type="submit">ذخیرهٔ آمار این هفته</x-filament::button>
        </form>
    </div>
</x-filament-panels::page>
