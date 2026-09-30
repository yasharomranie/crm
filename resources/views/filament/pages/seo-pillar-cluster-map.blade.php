<x-filament-panels::page>
    <div dir="rtl" class="space-y-6">
        <div class="max-w-sm">
            <label class="fi-fo-field-wrp-label text-sm font-medium text-gray-950 dark:text-white">پروژه</label>
            <select
                wire:model.live="projectId"
                class="fi-select-input mt-1 block w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white"
            >
                <option value="">— یک پروژه انتخاب کن —</option>
                @foreach ($this->projects() as $project)
                    <option value="{{ $project->id }}" @selected($projectId === $project->id)>{{ $project->name }}</option>
                @endforeach
            </select>
        </div>

        @if (! $projectId)
            <p class="text-sm text-gray-500 dark:text-gray-400">برای دیدن نقشه‌ی موضوعی، ابتدا یک پروژه انتخاب کن.</p>
        @elseif ($this->map()?->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400">این پروژه هنوز هیچ خوشه‌ی موضوعی یا کلمه‌ی کلیدی‌ای ندارد.</p>
        @else
            <div class="space-y-3">
                @foreach ($this->map() as $group)
                    <details class="group rounded-lg border border-gray-200 dark:border-white/10">
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-3 p-4 [&::-webkit-details-marker]:hidden">
                            <div class="flex items-center gap-2">
                                <svg class="h-4 w-4 shrink-0 text-gray-400 transition-transform group-open:-rotate-90" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                                </svg>
                                <h3 class="text-base font-bold text-gray-950 dark:text-white">{{ $group['name'] }}</h3>
                                @if (! empty($group['warnings']))
                                    <span class="inline-flex items-center gap-1 rounded-full bg-warning-50 px-2 py-0.5 text-xs text-warning-700 dark:bg-warning-500/10 dark:text-warning-400">
                                        ⚠ {{ count($group['warnings']) }} هشدار
                                    </span>
                                @endif
                            </div>
                            <span class="text-xs text-gray-500 dark:text-gray-400">
                                {{ $group['profiles']->count() + $group['suggestions']->count() }} کلمه · {{ $group['contentItems']->count() }} محتوا
                            </span>
                        </summary>

                        <div class="border-t border-gray-200 p-4 dark:border-white/10">
                            @if (! empty($group['warnings']))
                                <div class="space-y-1.5">
                                    @foreach ($group['warnings'] as $warning)
                                        <div class="flex items-start gap-2 rounded-md bg-warning-50 px-3 py-2 text-xs text-warning-700 dark:bg-warning-500/10 dark:text-warning-400">
                                            <span>⚠</span>
                                            <span>احتمال هم‌خواری بین «{{ $warning['a'] }}» و «{{ $warning['b'] }}» (شباهت {{ $warning['similarity'] * 100 }}٪) - فقط راهنماست، تصمیم نهایی با شماست.</span>
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            @if ($group['profiles']->isNotEmpty())
                                <div class="mt-3">
                                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">کلمات واقعی (سرچ کنسول)</p>
                                    <div class="mt-1.5 flex flex-wrap gap-1.5">
                                        @foreach ($group['profiles'] as $profile)
                                            <x-filament::badge :color="$profile->cannibalization_risk?->getColor() ?? 'gray'">
                                                {{ $profile->query }}
                                                @if ($profile->cannibalization_risk && $profile->cannibalization_risk->value !== 'none')
                                                    ({{ $profile->cannibalization_risk->getLabel() }})
                                                @endif
                                            </x-filament::badge>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            @if ($group['suggestions']->isNotEmpty())
                                <div class="mt-3">
                                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">پیشنهادهای هوش مصنوعی</p>
                                    <div class="mt-1.5 flex flex-wrap gap-1.5">
                                        @foreach ($group['suggestions'] as $suggestion)
                                            <x-filament::badge color="gray">
                                                {{ $suggestion->keyword }} · {{ $suggestion->status->getLabel() }}
                                            </x-filament::badge>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            @if ($group['contentItems']->isNotEmpty())
                                <div class="mt-3">
                                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">محتوا</p>
                                    <div class="mt-1.5 flex flex-wrap gap-1.5">
                                        @foreach ($group['contentItems'] as $item)
                                            <x-filament::badge :color="$item->status->getColor()">
                                                {{ $item->title }} · {{ $item->status->getLabel() }}
                                            </x-filament::badge>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    </details>
                @endforeach
            </div>
        @endif
    </div>
</x-filament-panels::page>
