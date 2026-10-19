<x-filament-panels::page>
    @php($overview = $this->overview())
    @php($states = \App\Domain\Seo\WpConnector\WpFleetStatus::STATES)

    <div dir="rtl" class="space-y-6">
        <div class="flex flex-wrap gap-2">
            <button type="button" wire:click="filterBy(null)" @class(['rounded-lg border px-3 py-1.5 text-sm', 'border-primary-500 bg-primary-50 text-primary-700 dark:bg-primary-500/10 dark:text-primary-300' => ! $state, 'border-gray-200 text-gray-700 dark:border-white/10 dark:text-gray-300' => $state])>
                همه <span class="tabular-nums">({{ array_sum($overview['counts']) }})</span>
            </button>
            @foreach ($states as $key => $meta)
                <button type="button" wire:click="filterBy('{{ $key }}')" wire:key="state-{{ $key }}" @class(['rounded-lg border px-3 py-1.5 text-sm', 'border-primary-500 bg-primary-50 text-primary-700 dark:bg-primary-500/10 dark:text-primary-300' => $state === $key, 'border-gray-200 text-gray-700 dark:border-white/10 dark:text-gray-300' => $state !== $key])>
                    {{ $meta['label'] }} <span class="tabular-nums">({{ $overview['counts'][$key] }})</span>
                </button>
            @endforeach
        </div>

        <p class="text-xs text-gray-500 dark:text-gray-400">آخرین نسخه‌ی پلاگین در CRM: <span dir="ltr" class="tabular-nums">{{ $overview['latest_version'] }}</span>. سایتی که بیش از {{ \App\Models\SeoWpSite::STALE_AFTER_HOURS }} ساعت خبر نداده «ساکت» حساب می‌شود.</p>

        @if ($overview['rows']->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400">سایتی با این وضعیت نیست.</p>
        @else
            <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-white/10">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-xs text-gray-500 dark:bg-white/5 dark:text-gray-400">
                        <tr>
                            <th class="px-3 py-2 text-right font-medium">پروژه</th>
                            <th class="px-3 py-2 text-right font-medium">وضعیت</th>
                            <th class="px-3 py-2 text-right font-medium">پلاگین</th>
                            <th class="px-3 py-2 text-right font-medium">صفحه</th>
                            <th class="px-3 py-2 text-right font-medium">با مشکل</th>
                            <th class="px-3 py-2 text-right font-medium">آخرین خبر</th>
                            <th class="px-3 py-2 text-right font-medium">آخرین دریافت</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                        @foreach ($overview['rows'] as $row)
                            <tr wire:key="site-{{ $row['site_id'] }}">
                                <td class="px-3 py-2">
                                    <a href="{{ \App\Filament\Resources\SeoProjects\SeoProjectResource::getUrl('edit', ['record' => $row['project_id']]) }}" class="font-medium text-primary-600 hover:underline dark:text-primary-400">{{ $row['project'] }}</a>
                                    @if ($row['url'])
                                        <div dir="ltr" class="text-left text-xs text-gray-500 dark:text-gray-400">{{ $row['url'] }}</div>
                                    @endif
                                    @if ($row['core_update'] || $row['outdated_plugins'] || $row['not_found'] || $row['search_misses'] || $row['analytics'] || $row['analytics_error'])
                                        <details class="mt-1 text-xs text-gray-600 dark:text-gray-400">
                                            <summary class="cursor-pointer text-primary-600 dark:text-primary-400">جزئیات</summary>
                                            <div class="mt-1 space-y-1">
                                                @if ($row['analytics'])
                                                    <div>Google Analytics (۲۸ روز): {{ number_format($row['analytics']['sessions']) }} نشست، {{ number_format($row['analytics']['key_events']) }} تبدیل@if ($row['analytics']['ai_sessions'] > 0)، {{ number_format($row['analytics']['ai_sessions']) }} ورودی از دستیارهای هوش مصنوعی ({{ implode('، ', array_keys($row['analytics']['ai_sources'])) }})@endif.</div>
                                                @endif
                                                @if ($row['analytics_error'])
                                                    <div class="text-danger-600 dark:text-danger-400">خطای Analytics: {{ $row['analytics_error'] }}</div>
                                                @endif
                                                @if ($row['core_update'])
                                                    <div>وردپرس نسخه‌ی <span dir="ltr">{{ $row['core_update'] }}</span> آماده‌ی به‌روزرسانی است.</div>
                                                @endif
                                                @if ($row['outdated_plugins'])
                                                    <div>افزونه‌های قدیمی: @foreach ($row['outdated_plugins'] as $plugin){{ $plugin['name'] }}{{ $plugin['new_version'] ? ' ('.$plugin['new_version'].')' : '' }}{{ $loop->last ? '' : '، ' }}@endforeach</div>
                                                @endif
                                                @if ($row['not_found'])
                                                    <div>پربازدیدترین آدرس‌های «۴۰۴» ({{ number_format($row['not_found_total']) }} آدرس):
                                                        @foreach ($row['not_found'] as $notFound)
                                                            <div dir="ltr" class="text-left">{{ $notFound['path'] }} <span class="text-gray-400">× {{ $notFound['hits'] }}</span></div>
                                                        @endforeach
                                                    </div>
                                                @endif
                                                @if ($row['search_misses'])
                                                    <div>در سایت جست‌وجو شد و چیزی پیدا نشد: @foreach ($row['search_misses'] as $miss){{ $miss['term'] }} ({{ $miss['misses'] }}){{ $loop->last ? '' : '، ' }}@endforeach</div>
                                                @endif
                                            </div>
                                        </details>
                                    @endif
                                </td>
                                <td class="px-3 py-2">
                                    <x-filament::badge :color="$row['state_color']">{{ $row['state_label'] }}</x-filament::badge>
                                    @if ($row['ssl_days_left'] !== null && $row['ssl_days_left'] <= \App\Domain\Seo\SiteHealth\SiteHealthMonitor::SSL_ALERT_DAYS[0])
                                        <div class="mt-1 text-xs text-warning-600 dark:text-warning-400">{{ $row['ssl_days_left'] >= 0 ? 'SSL: '.$row['ssl_days_left'].' روز تا انقضا' : 'SSL منقضی شده' }}</div>
                                    @endif
                                    @if ($row['last_error'])
                                        <div class="mt-1 max-w-xs text-xs text-danger-600 dark:text-danger-400">{{ \Illuminate\Support\Str::limit($row['last_error'], 120) }}</div>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-xs text-gray-600 dark:text-gray-400">
                                    <span dir="ltr" class="tabular-nums">{{ $row['plugin_version'] ?? '—' }}</span>
                                    @if ($row['seo_plugin'])
                                        <div>{{ $row['seo_plugin'] }}{{ $row['has_woocommerce'] ? ' + ووکامرس' : '' }}</div>
                                    @endif
                                </td>
                                <td class="px-3 py-2 tabular-nums text-gray-700 dark:text-gray-300">{{ number_format($row['posts']) }}</td>
                                <td class="px-3 py-2 tabular-nums text-gray-700 dark:text-gray-300">{{ number_format($row['posts_with_issues']) }}</td>
                                <td class="px-3 py-2 text-xs text-gray-600 dark:text-gray-400">{{ $row['last_seen_at']?->diffForHumans() ?? '—' }}</td>
                                <td class="px-3 py-2 text-xs text-gray-600 dark:text-gray-400">{{ $row['last_sync_at']?->diffForHumans() ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-filament-panels::page>
