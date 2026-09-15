<x-filament-panels::page>
    <div class="space-y-4">
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @if ($this->canSelectOtherUsers())
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-950 dark:text-white">کارمند</label>
                        <select wire:model.live="userId" class="fi-input block w-full rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-white/5 dark:text-white">
                            @foreach ($this->getUserOptions() as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-950 dark:text-white">فرم</label>
                    <select wire:model.live="formTemplateId" class="fi-input block w-full rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-white/5 dark:text-white">
                        <option value="">— انتخاب کنید —</option>
                        @foreach ($this->getFormTemplateOptions() as $id => $title)
                            <option value="{{ $id }}">{{ $title }}</option>
                        @endforeach
                    </select>
                    @if ($this->getFormTemplateOptions()->isEmpty())
                        <p class="mt-1 text-xs text-gray-500">
                            هنوز فرمی ساخته نشده؛
                            <a href="{{ \App\Filament\Resources\FormTemplates\FormTemplateResource::getUrl() }}" class="underline">از اینجا بسازید</a>.
                        </p>
                    @endif
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-950 dark:text-white">بازهٔ نمایش</label>
                    <div class="flex gap-1.5">
                        <button
                            type="button"
                            wire:click="$set('periodType', 'weekly')"
                            @class([
                                'flex-1 rounded-lg px-3 py-1.5 text-sm font-medium transition',
                                'bg-primary-600 text-white' => $periodType === 'weekly',
                                'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-white/5 dark:text-gray-300' => $periodType !== 'weekly',
                            ])
                        >هفتگی</button>
                        <button
                            type="button"
                            wire:click="$set('periodType', 'monthly')"
                            @class([
                                'flex-1 rounded-lg px-3 py-1.5 text-sm font-medium transition',
                                'bg-primary-600 text-white' => $periodType === 'monthly',
                                'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-white/5 dark:text-gray-300' => $periodType !== 'monthly',
                            ])
                        >ماهانه</button>
                    </div>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-950 dark:text-white">جابه‌جایی</label>
                    <div class="flex gap-1.5">
                        <button type="button" wire:click="nextPeriod" class="rounded-lg bg-gray-100 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-200 dark:bg-white/5 dark:text-gray-300">بعدی »</button>
                        <button type="button" wire:click="goToToday" class="flex-1 rounded-lg bg-gray-100 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-200 dark:bg-white/5 dark:text-gray-300">امروز</button>
                        <button type="button" wire:click="previousPeriod" class="rounded-lg bg-gray-100 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-200 dark:bg-white/5 dark:text-gray-300">« قبلی</button>
                    </div>
                </div>
            </div>
        </div>

        @if (! $this->formTemplateId)
            <div class="rounded-xl border border-dashed border-gray-300 p-10 text-center text-gray-500 dark:border-white/10 dark:text-gray-400">
                برای مشاهدهٔ جدول، یک فرم را از بالا انتخاب کنید.
            </div>
        @else
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <h3 class="text-base font-semibold text-gray-950 dark:text-white">{{ $this->getPeriodLabel() }}</h3>

                    <div class="flex flex-wrap items-center gap-3 text-xs text-gray-500 dark:text-gray-400">
                        <span class="flex items-center gap-1"><span class="h-2.5 w-2.5 rounded-full bg-success-500"></span> تأیید شده</span>
                        <span class="flex items-center gap-1"><span class="h-2.5 w-2.5 rounded-full bg-warning-500"></span> در انتظار تأیید</span>
                        <span class="flex items-center gap-1"><span class="h-2.5 w-2.5 rounded-full bg-danger-500"></span> رد شده</span>
                        <span class="flex items-center gap-1"><span class="h-2.5 w-2.5 rounded-full border border-gray-400"></span> ثبت نشده</span>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[640px] table-fixed border-separate [border-spacing:0.35rem]">
                        <thead>
                            <tr>
                                @foreach ($this->getWeekdayHeaders() as $weekday)
                                    <th class="pb-1 text-xs font-medium text-gray-500 dark:text-gray-400">{{ $weekday }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($this->getWeeks() as $week)
                                <tr>
                                    @foreach ($week as $cell)
                                        @php
                                            $submission = $cell['submission'];
                                            $statusColors = [
                                                'success' => 'border-success-300 bg-success-50 dark:border-success-500/30 dark:bg-success-950/40',
                                                'warning' => 'border-warning-300 bg-warning-50 dark:border-warning-500/30 dark:bg-warning-950/40',
                                                'danger' => 'border-danger-300 bg-danger-50 dark:border-danger-500/30 dark:bg-danger-950/40',
                                                'gray' => 'border-gray-300 bg-gray-50 dark:border-white/10 dark:bg-white/5',
                                            ];
                                            $colorClass = $submission
                                                ? ($statusColors[$submission->status->getColor()] ?? $statusColors['gray'])
                                                : ($cell['inPeriod'] && ! $cell['isFuture'] ? 'border-dashed border-gray-300 dark:border-white/10' : 'border-transparent');
                                        @endphp
                                        <td class="align-top">
                                            @if ($submission)
                                                <a
                                                    href="{{ $this->getSubmissionUrl($submission) }}"
                                                    wire:navigate
                                                    class="block h-20 rounded-lg border p-2 text-xs transition hover:shadow {{ $colorClass }} {{ $cell['isToday'] ? 'ring-2 ring-primary-500' : '' }}"
                                                >
                                                    <div class="flex items-center justify-between">
                                                        <span class="font-semibold {{ $cell['inPeriod'] ? 'text-gray-950 dark:text-white' : 'text-gray-400' }}">{{ \Illuminate\Support\Number::toPersian($cell['jalali']->format('%d')) }}</span>
                                                        <x-filament::badge :color="$submission->status->getColor()" size="xs">
                                                            {{ $submission->status->getLabel() }}
                                                        </x-filament::badge>
                                                    </div>
                                                    <div class="mt-1 truncate text-gray-600 dark:text-gray-300">{{ $submission->reference_code }}</div>
                                                </a>
                                            @else
                                                <div class="flex h-20 flex-col justify-between rounded-lg border p-2 text-xs {{ $colorClass }} {{ $cell['isToday'] ? 'ring-2 ring-primary-500' : '' }} {{ ! $cell['inPeriod'] ? 'opacity-40' : '' }}">
                                                    <span class="font-semibold {{ $cell['inPeriod'] ? 'text-gray-950 dark:text-white' : 'text-gray-400' }}">{{ \Illuminate\Support\Number::toPersian($cell['jalali']->format('%d')) }}</span>
                                                    @if ($cell['inPeriod'] && ! $cell['isFuture'])
                                                        <span class="text-warning-600 dark:text-warning-400">ثبت نشده</span>
                                                    @endif
                                                </div>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
