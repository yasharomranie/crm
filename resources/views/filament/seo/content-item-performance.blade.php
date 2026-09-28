<div class="fi-section-content-ctn space-y-4" dir="rtl">
    <div class="text-sm text-gray-500 dark:text-gray-400">
        <span class="font-medium text-gray-950 dark:text-white">آدرس منتشرشده:</span>
        <a href="{{ $item->published_url }}" target="_blank" rel="noopener" class="text-primary-600 hover:underline break-all" dir="ltr">
            {{ $item->published_url }}
        </a>
    </div>

    @if ($summary['days_since_publish'] === null)
        <p class="text-sm text-gray-500 dark:text-gray-400">هنوز داده‌ای برای این محتوا ثبت نشده است.</p>
    @else
        <dl class="grid grid-cols-2 gap-4 sm:grid-cols-4">
            <div class="rounded-lg border border-gray-200 p-3 dark:border-white/10">
                <dt class="text-xs text-gray-500 dark:text-gray-400">کلیک (از زمان انتشار)</dt>
                <dd class="mt-1 text-lg font-semibold text-gray-950 dark:text-white">{{ number_format($summary['clicks']) }}</dd>
            </div>
            <div class="rounded-lg border border-gray-200 p-3 dark:border-white/10">
                <dt class="text-xs text-gray-500 dark:text-gray-400">نمایش (از زمان انتشار)</dt>
                <dd class="mt-1 text-lg font-semibold text-gray-950 dark:text-white">{{ number_format($summary['impressions']) }}</dd>
            </div>
            <div class="rounded-lg border border-gray-200 p-3 dark:border-white/10">
                <dt class="text-xs text-gray-500 dark:text-gray-400">میانگین موقعیت</dt>
                <dd class="mt-1 text-lg font-semibold text-gray-950 dark:text-white">
                    {{ $summary['avg_position'] !== null ? number_format($summary['avg_position'], 1) : '—' }}
                </dd>
            </div>
            <div class="rounded-lg border border-gray-200 p-3 dark:border-white/10">
                <dt class="text-xs text-gray-500 dark:text-gray-400">روز از انتشار</dt>
                <dd class="mt-1 text-lg font-semibold text-gray-950 dark:text-white">{{ $summary['days_since_publish'] }}</dd>
            </div>
        </dl>

        @if ($summary['clicks'] === 0 && $summary['impressions'] === 0)
            <p class="text-xs text-gray-500 dark:text-gray-400">
                این آدرس هنوز در داده‌های Search Console دیده نشده - ممکن است هنوز همگام‌سازی نشده یا گوگل هنوز آن را ایندکس نکرده باشد.
            </p>
        @endif
    @endif
</div>
