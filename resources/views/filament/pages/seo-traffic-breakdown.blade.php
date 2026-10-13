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
            <p class="text-sm text-gray-500 dark:text-gray-400">برای دیدن ترکیب ترافیک، ابتدا یک پروژه انتخاب کن.</p>
        @else
            @php
                $countries = $this->topCountries();
                $searchTypes = $this->bySearchType();
                $searchAppearance = $this->bySearchAppearance();
            @endphp

            <div class="grid gap-4 lg:grid-cols-3">
                <div class="rounded-lg border border-gray-200 p-3 dark:border-white/10">
                    <p class="mb-2 text-xs font-medium text-gray-500 dark:text-gray-400">کشور بازدیدکننده (۲۸ روز اخیر)</p>
                    @if ($countries->isEmpty())
                        <p class="text-sm text-gray-400">هنوز داده‌ای همگام‌سازی نشده است.</p>
                    @else
                        <table class="w-full text-sm">
                            <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                                @foreach ($countries as $row)
                                    <tr>
                                        <td class="py-1.5 text-gray-700 dark:text-gray-300" dir="ltr">{{ $row['country'] }}</td>
                                        <td class="py-1.5 text-left text-xs text-gray-500 dark:text-gray-400" dir="ltr">{{ number_format($row['clicks']) }} کلیک</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>

                <div class="rounded-lg border border-gray-200 p-3 dark:border-white/10">
                    <p class="mb-2 text-xs font-medium text-gray-500 dark:text-gray-400">نوع جستجو (۲۸ روز اخیر)</p>
                    @if ($searchTypes->isEmpty())
                        <p class="text-sm text-gray-400">در این بازه ترافیکی از جستجوی تصویر/ویدیو/اخبار/Discover ثبت نشده است.</p>
                    @else
                        <table class="w-full text-sm">
                            <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                                @foreach ($searchTypes as $row)
                                    <tr>
                                        <td class="py-1.5 text-gray-700 dark:text-gray-300">{{ \App\Enums\SeoSearchType::tryFrom($row['search_type'])?->getLabel() ?? $row['search_type'] }}</td>
                                        <td class="py-1.5 text-left text-xs text-gray-500 dark:text-gray-400" dir="ltr">{{ number_format($row['clicks']) }} کلیک</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>

                <div class="rounded-lg border border-gray-200 p-3 dark:border-white/10">
                    <p class="mb-2 text-xs font-medium text-gray-500 dark:text-gray-400">ظاهر در نتایج گوگل (آخرین همگام‌سازی)</p>
                    @if ($searchAppearance->isEmpty())
                        <p class="text-sm text-gray-400">هنوز داده‌ای همگام‌سازی نشده است.</p>
                    @else
                        <table class="w-full text-sm">
                            <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                                @foreach ($searchAppearance as $row)
                                    <tr>
                                        <td class="py-1.5 text-gray-700 dark:text-gray-300" dir="ltr">{{ $row['search_appearance'] }}</td>
                                        <td class="py-1.5 text-left text-xs text-gray-500 dark:text-gray-400" dir="ltr">{{ number_format($row['clicks']) }} کلیک</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
