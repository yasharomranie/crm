<div class="fi-section-content-ctn" dir="rtl">
    @if ($audit->performance_score !== null)
        <div class="mb-4 grid grid-cols-3 gap-3 text-sm">
            <div class="rounded-lg border border-gray-200 p-3 dark:border-white/10">
                <p class="text-xs text-gray-500 dark:text-gray-400">امتیاز سرعت (موبایل)</p>
                <p class="mt-1 text-lg font-bold text-gray-950 dark:text-white">{{ $audit->performance_score }}/۱۰۰</p>
            </div>
            @if ($audit->core_web_vitals)
                <div class="rounded-lg border border-gray-200 p-3 dark:border-white/10">
                    <p class="text-xs text-gray-500 dark:text-gray-400">LCP</p>
                    <p class="mt-1 text-lg font-bold text-gray-950 dark:text-white" dir="ltr">
                        {{ $audit->core_web_vitals['lcp_ms'] !== null ? round($audit->core_web_vitals['lcp_ms'] / 1000, 1).'s' : '—' }}
                    </p>
                </div>
                <div class="rounded-lg border border-gray-200 p-3 dark:border-white/10">
                    <p class="text-xs text-gray-500 dark:text-gray-400">CLS</p>
                    <p class="mt-1 text-lg font-bold text-gray-950 dark:text-white" dir="ltr">
                        {{ $audit->core_web_vitals['cls'] ?? '—' }}
                    </p>
                </div>
            @endif
        </div>
    @elseif ($audit->performance_error)
        <div class="mb-4 flex items-start gap-2 rounded-md bg-danger-50 px-3 py-2 text-xs text-danger-700 dark:bg-danger-500/10 dark:text-danger-400">
            <span>⚠</span>
            <span>تحلیل سرعت صفحه (PageSpeed Insights) ناموفق بود: {{ $audit->performance_error }}</span>
        </div>
    @endif

    @if ($weakestLinkedPages->isNotEmpty())
        <div class="mb-4">
            <p class="mb-1.5 text-xs font-medium text-gray-500 dark:text-gray-400">ضعیف‌ترین پیوندهای داخلی (صفحاتی که در این بررسی حداکثر یک لینک داخلی به آن‌ها پیدا شد)</p>
            <table class="w-full text-sm">
                <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                    @foreach ($weakestLinkedPages as $page)
                        <tr>
                            <td class="py-1.5 max-w-xs truncate text-xs text-gray-500 dark:text-gray-400" dir="ltr">{{ $page->to_url }}</td>
                            <td class="py-1.5 text-xs text-gray-500 dark:text-gray-400">{{ \Illuminate\Support\Number::toPersian($page->incoming) }} لینک داخلی</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if ($issues->isEmpty())
        <p class="text-sm text-gray-500 dark:text-gray-400">هیچ مشکلی در این بررسی یافت نشد.</p>
    @else
        <div class="max-h-96 overflow-y-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200 text-gray-500 dark:border-white/10 dark:text-gray-400">
                        <th class="py-1.5 text-right font-medium">شدت</th>
                        <th class="py-1.5 text-right font-medium">نوع مشکل</th>
                        <th class="py-1.5 text-right font-medium">آدرس</th>
                        <th class="py-1.5 text-right font-medium">جزئیات</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                    @foreach ($issues as $issue)
                        <tr>
                            <td class="py-1.5">
                                <x-filament::badge :color="$issue->severity->getColor()">
                                    {{ $issue->severity->getLabel() }}
                                </x-filament::badge>
                            </td>
                            <td class="py-1.5 text-gray-700 dark:text-gray-300">{{ $issue->type->getLabel() }}</td>
                            <td class="py-1.5 max-w-xs truncate text-xs text-gray-500 dark:text-gray-400" dir="ltr">
                                {{ $issue->url ?? '—' }}
                            </td>
                            <td class="py-1.5 text-xs text-gray-500 dark:text-gray-400">{{ $issue->details ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
