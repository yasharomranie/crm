<x-filament-panels::page>
    <div class="space-y-4">
        @php $main = $this->getMainAssistantStats(); @endphp
        <div class="fi-section rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900 sm:p-6">
            <div class="flex items-center justify-between gap-3">
                <h3 class="text-base font-semibold text-gray-950 dark:text-white">دستیار هوشمند مدیریتی</h3>
                <span class="text-xs text-gray-500 dark:text-gray-400">۳۰ روز گذشته، همهٔ کاربران</span>
            </div>

            <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                @foreach ([
                    ['پرسش', $main['questions']],
                    ['گفتگوی جدید', $main['conversations']],
                    ['توکن ورودی', $main['prompt_tokens']],
                    ['توکن خروجی', $main['completion_tokens']],
                    ['پسندیده', $main['feedback_up']],
                    ['نپسندیده', $main['feedback_down']],
                ] as [$label, $value])
                    <div class="rounded-lg bg-gray-50 px-3 py-2 text-center dark:bg-white/5">
                        <div class="text-lg font-bold text-gray-950 dark:text-white">{{ \Illuminate\Support\Number::toPersian(number_format($value)) }}</div>
                        <div class="text-[11px] text-gray-500 dark:text-gray-400">{{ $label }}</div>
                    </div>
                @endforeach
            </div>

            @if (! empty($main['top_tools']))
                <div class="mt-4">
                    <p class="mb-2 text-xs font-medium text-gray-500 dark:text-gray-400">پرکاربردترین ابزارها</p>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($main['top_tools'] as $row)
                            <span class="rounded-full bg-primary-50 px-3 py-1 text-xs text-primary-700 dark:bg-primary-500/10 dark:text-primary-300">
                                {{ $row['tool'] }} · {{ \Illuminate\Support\Number::toPersian($row['calls']) }}
                            </span>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        @php $recentCalls = $this->getRecentToolCalls(); @endphp
        @if ($recentCalls->isNotEmpty())
            <div class="fi-section rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900 sm:p-6">
                <h3 class="text-base font-semibold text-gray-950 dark:text-white">آخرین فراخوانی ابزارها</h3>
                <div class="mt-3 overflow-x-auto">
                    <table class="w-full text-start text-sm">
                        <thead class="text-xs text-gray-500 dark:text-gray-400">
                            <tr>
                                <th class="py-1 pe-3 text-start font-medium">زمان</th>
                                <th class="py-1 pe-3 text-start font-medium">کاربر</th>
                                <th class="py-1 pe-3 text-start font-medium">ابزار</th>
                                <th class="py-1 text-start font-medium">وضعیت</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($recentCalls as $call)
                                <tr class="border-t border-gray-100 dark:border-white/5">
                                    <td class="py-1.5 pe-3">{{ $call['when'] }}</td>
                                    <td class="py-1.5 pe-3">{{ $call['user'] }}</td>
                                    <td class="py-1.5 pe-3" dir="ltr">{{ $call['tool'] }}</td>
                                    <td class="py-1.5">
                                        <span @class(['rounded-full px-2 py-0.5 text-xs', 'bg-danger-50 text-danger-700 dark:bg-danger-500/10 dark:text-danger-300' => $call['denied'], 'bg-success-50 text-success-700 dark:bg-success-500/10 dark:text-success-300' => ! $call['denied']])>
                                            {{ $call['denied'] ? 'رد شد (بدون دسترسی)' : 'انجام شد' }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        @forelse ($this->getProfileUsageStats() as $row)
            @php $profile = $row['profile']; @endphp
            <div class="fi-section rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900 sm:p-6">
                <div class="flex items-center gap-3">
                    <x-ai-assistant-avatar :shape="$profile->avatar_shape" :color="$profile->avatar_color" :size="40" :animated="false" />
                    <div class="min-w-0">
                        <h3 class="truncate text-base font-semibold text-gray-950 dark:text-white">{{ $profile->displayName() }}</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $profile->is_shared ? 'عمومی' : 'خصوصی' }}</p>
                    </div>
                </div>

                <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-7">
                    <div class="rounded-lg bg-gray-50 px-3 py-2 text-center dark:bg-white/5">
                        <div class="text-lg font-bold text-gray-950 dark:text-white">{{ \Illuminate\Support\Number::toPersian($row['message_count']) }}</div>
                        <div class="text-[11px] text-gray-500 dark:text-gray-400">پیام</div>
                    </div>
                    <div class="rounded-lg bg-gray-50 px-3 py-2 text-center dark:bg-white/5">
                        <div class="text-lg font-bold text-gray-950 dark:text-white">{{ \Illuminate\Support\Number::toPersian($row['conversation_count']) }}</div>
                        <div class="text-[11px] text-gray-500 dark:text-gray-400">گفتگو</div>
                    </div>
                    <div class="rounded-lg bg-gray-50 px-3 py-2 text-center dark:bg-white/5">
                        <div class="text-lg font-bold text-gray-950 dark:text-white">{{ \Illuminate\Support\Number::toPersian($row['consultations_asked']) }}</div>
                        <div class="text-[11px] text-gray-500 dark:text-gray-400">مشورت گرفته</div>
                    </div>
                    <div class="rounded-lg bg-gray-50 px-3 py-2 text-center dark:bg-white/5">
                        <div class="text-lg font-bold text-gray-950 dark:text-white">{{ \Illuminate\Support\Number::toPersian($row['consultations_answered']) }}</div>
                        <div class="text-[11px] text-gray-500 dark:text-gray-400">مشورت داده</div>
                    </div>
                    <div class="rounded-lg bg-success-50 px-3 py-2 text-center dark:bg-success-500/10">
                        <div class="text-lg font-bold text-success-600 dark:text-success-400">{{ \Illuminate\Support\Number::toPersian($row['feedback_up']) }}</div>
                        <div class="text-[11px] text-gray-500 dark:text-gray-400">پسندیده</div>
                    </div>
                    <div class="rounded-lg bg-danger-50 px-3 py-2 text-center dark:bg-danger-500/10">
                        <div class="text-lg font-bold text-danger-600 dark:text-danger-400">{{ \Illuminate\Support\Number::toPersian($row['feedback_down']) }}</div>
                        <div class="text-[11px] text-gray-500 dark:text-gray-400">نپسندیده</div>
                    </div>
                    <div class="rounded-lg bg-gray-50 px-3 py-2 text-center dark:bg-white/5">
                        <div class="text-lg font-bold text-gray-950 dark:text-white">{{ \Illuminate\Support\Number::toPersian($row['watched_sources_count']) }} / {{ \Illuminate\Support\Number::toPersian($row['active_scheduled_prompts_count']) }}</div>
                        <div class="text-[11px] text-gray-500 dark:text-gray-400">منبع رصدی / اجرای خودکار فعال</div>
                    </div>
                </div>
            </div>
        @empty
            <div class="fi-section rounded-xl border border-gray-200 bg-white p-6 text-center text-sm text-gray-500 shadow-sm dark:border-white/10 dark:bg-gray-900 dark:text-gray-400">
                هنوز هیچ دستیار اختصاصی‌ای ساخته نشده.
            </div>
        @endforelse
    </div>
</x-filament-panels::page>
