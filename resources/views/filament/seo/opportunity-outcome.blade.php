<div class="fi-section-content-ctn space-y-4" dir="rtl">
    <div class="flex items-center gap-2">
        <span class="text-sm text-gray-500 dark:text-gray-400">وضعیت:</span>
        <x-filament::badge :color="$outcome->verdict->getColor()">
            {{ $outcome->verdict->getLabel() }}
        </x-filament::badge>
    </div>

    <div class="grid grid-cols-2 gap-4">
        <div class="rounded-lg border border-gray-200 p-3 dark:border-white/10">
            <h4 class="text-xs font-semibold text-gray-500 dark:text-gray-400">قبل از اقدام</h4>
            <dl class="mt-2 space-y-1 text-sm">
                <div class="flex justify-between"><dt>کلیک (۲۸ روزه)</dt><dd class="font-medium">{{ number_format($outcome->baseline_clicks) }}</dd></div>
                <div class="flex justify-between"><dt>نمایش (۲۸ روزه)</dt><dd class="font-medium">{{ number_format($outcome->baseline_impressions) }}</dd></div>
                <div class="flex justify-between"><dt>میانگین موقعیت</dt><dd class="font-medium">{{ $outcome->baseline_position !== null ? number_format((float) $outcome->baseline_position, 1) : '—' }}</dd></div>
            </dl>
        </div>

        <div class="rounded-lg border border-gray-200 p-3 dark:border-white/10">
            <h4 class="text-xs font-semibold text-gray-500 dark:text-gray-400">بعد از اقدام</h4>
            @if ($outcome->followup_measured_at === null)
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">هنوز زمان کافی نگذشته است.</p>
            @else
                <dl class="mt-2 space-y-1 text-sm">
                    <div class="flex justify-between"><dt>کلیک (۲۸ روزه)</dt><dd class="font-medium">{{ number_format($outcome->followup_clicks) }}</dd></div>
                    <div class="flex justify-between"><dt>نمایش (۲۸ روزه)</dt><dd class="font-medium">{{ number_format($outcome->followup_impressions) }}</dd></div>
                    <div class="flex justify-between"><dt>میانگین موقعیت</dt><dd class="font-medium">{{ $outcome->followup_position !== null ? number_format((float) $outcome->followup_position, 1) : '—' }}</dd></div>
                </dl>
            @endif
        </div>
    </div>
</div>
