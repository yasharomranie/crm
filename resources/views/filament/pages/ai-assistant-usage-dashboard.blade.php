<x-filament-panels::page>
    <div class="space-y-4">
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
