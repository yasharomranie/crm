<div class="fi-section-content-ctn" dir="rtl">
    @if ($error ?? null)
        <p class="text-sm text-danger-600">{{ $error }}</p>
    @else
        <div class="mb-4 flex items-center gap-2">
            <x-filament::badge :color="$inspection->isIndexed() ? 'success' : 'danger'">
                {{ $inspection->isIndexed() ? 'ایندکس شده' : 'ایندکس نشده' }}
            </x-filament::badge>
            <span class="text-xs text-gray-500 dark:text-gray-400" dir="ltr">{{ $inspection->page_url }}</span>
        </div>

        <p class="text-sm text-gray-700 dark:text-gray-300">{{ $inspection->explanationInPersian() }}</p>

        <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
            <div class="rounded-lg border border-gray-200 p-3 dark:border-white/10">
                <p class="text-xs text-gray-500 dark:text-gray-400">آخرین کراول گوگل</p>
                <p class="mt-1 font-medium text-gray-950 dark:text-white">
                    {{ $inspection->last_crawl_time?->translatedFormat('Y/m/d H:i') ?? 'هنوز کراول نشده' }}
                </p>
            </div>
            <div class="rounded-lg border border-gray-200 p-3 dark:border-white/10">
                <p class="text-xs text-gray-500 dark:text-gray-400">زمان این بررسی</p>
                <p class="mt-1 font-medium text-gray-950 dark:text-white">
                    {{ $inspection->inspected_at->translatedFormat('Y/m/d H:i') }}
                </p>
            </div>
        </div>
    @endif
</div>
