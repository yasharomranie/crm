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
            <p class="text-sm text-gray-500 dark:text-gray-400">برای دیدن صف اقدام، ابتدا یک پروژه انتخاب کن.</p>
        @else
            @php($items = $this->items())

            @if ($items->isEmpty())
                <p class="text-sm text-gray-500 dark:text-gray-400">در حال حاضر هیچ فرصت، مشکل باز یا پیشنهاد در انتظار تصمیم برای این پروژه نیست.</p>
            @else
                <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-white/10">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 bg-gray-50 text-gray-500 dark:border-white/10 dark:bg-white/5 dark:text-gray-400">
                                <th class="px-3 py-2 text-right font-medium">منبع</th>
                                <th class="px-3 py-2 text-right font-medium">عنوان</th>
                                <th class="px-3 py-2 text-right font-medium">جزئیات</th>
                                <th class="px-3 py-2 text-right font-medium">آدرس</th>
                                <th class="px-3 py-2 text-right font-medium">امتیاز</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                            @foreach ($items as $item)
                                <tr>
                                    @php
                                        $sourceLabel = match ($item->source) {
                                            'technical_issue' => 'مشکل تکنیکال',
                                            'keyword_suggestion' => 'پیشنهاد کلمه کلیدی',
                                            default => 'فرصت سئو',
                                        };
                                        $sourceColor = match ($item->source) {
                                            'technical_issue' => 'danger',
                                            'keyword_suggestion' => 'info',
                                            default => 'warning',
                                        };
                                    @endphp
                                    <td class="px-3 py-2">
                                        <x-filament::badge :color="$sourceColor">
                                            {{ $sourceLabel }}
                                        </x-filament::badge>
                                    </td>
                                    <td class="px-3 py-2 text-gray-700 dark:text-gray-300">{{ $item->title }}</td>
                                    <td class="px-3 py-2 max-w-xs truncate text-xs text-gray-500 dark:text-gray-400">{{ $item->detail ?? '—' }}</td>
                                    <td class="px-3 py-2 max-w-xs truncate text-xs text-gray-500 dark:text-gray-400" dir="ltr">{{ $item->url ?? '—' }}</td>
                                    <td class="px-3 py-2 text-xs text-gray-500 dark:text-gray-400" dir="ltr">{{ number_format($item->score, 1) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        @endif
    </div>
</x-filament-panels::page>
