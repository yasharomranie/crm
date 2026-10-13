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
            <p class="text-sm text-gray-500 dark:text-gray-400">برای دیدن تحلیل Sitemap، ابتدا یک پروژه انتخاب کن.</p>
        @else
            @php
                $overview = $this->overview();
                $pages = $this->pages();
            @endphp

            @if ($overview['total'] === 0)
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    هنوز هیچ sitemap برای این پروژه همگام‌سازی نشده است.
                    @if ($this->editProjectUrl())
                        از <a href="{{ $this->editProjectUrl() }}" class="text-primary-600 hover:underline">صفحه‌ی ویرایش پروژه</a> آدرس Sitemap را ثبت و دکمه‌ی «همگام‌سازی Sitemap الان» را بزن.
                    @endif
                </p>
            @else
                @include('filament.components.chartjs-assets')

                {{-- خلاصه‌ی سلامت --}}
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                    <div class="rounded-lg border border-gray-200 p-3 dark:border-white/10">
                        <p class="text-xs text-gray-500 dark:text-gray-400">کل صفحات</p>
                        <p class="mt-1 text-lg font-bold text-gray-950 dark:text-white">{{ number_format($overview['total']) }}</p>
                    </div>
                    <div class="rounded-lg border border-gray-200 p-3 dark:border-white/10">
                        <p class="text-xs text-gray-500 dark:text-gray-400">آدرس‌های تکراری</p>
                        <p class="mt-1 text-lg font-bold {{ $overview['duplicates'] > 0 ? 'text-danger-600' : 'text-gray-950 dark:text-white' }}">{{ number_format($overview['duplicates']) }}</p>
                    </div>
                    <div class="rounded-lg border border-gray-200 p-3 dark:border-white/10">
                        <p class="text-xs text-gray-500 dark:text-gray-400">بدون lastmod</p>
                        <p class="mt-1 text-lg font-bold text-gray-950 dark:text-white">{{ number_format($overview['missing_lastmod']) }}</p>
                    </div>
                </div>

                @php($googleStatus = $this->googleStatus())
                @if ($googleStatus)
                    <div class="rounded-lg border border-gray-200 p-3 dark:border-white/10">
                        <p class="mb-2 text-xs font-medium text-gray-500 dark:text-gray-400">
                            وضعیت Sitemap نزد گوگل
                            @if ($this->googleStatusCheckedAt())
                                <span class="text-gray-400">(آخرین بررسی: {{ $this->googleStatusCheckedAt()->translatedFormat('Y/m/d H:i') }})</span>
                            @endif
                        </p>
                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                            <div>
                                <p class="text-xs text-gray-500 dark:text-gray-400">خطا</p>
                                <p class="mt-1 text-lg font-bold {{ ($googleStatus['errors'] ?? '0') !== '0' ? 'text-danger-600' : 'text-gray-950 dark:text-white' }}">{{ $googleStatus['errors'] ?? '۰' }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 dark:text-gray-400">هشدار</p>
                                <p class="mt-1 text-lg font-bold {{ ($googleStatus['warnings'] ?? '0') !== '0' ? 'text-warning-600' : 'text-gray-950 dark:text-white' }}">{{ $googleStatus['warnings'] ?? '۰' }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 dark:text-gray-400">آخرین دریافت گوگل</p>
                                <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">{{ $googleStatus['lastDownloaded'] ?? '—' }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 dark:text-gray-400">در انتظار بررسی</p>
                                <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">{{ ($googleStatus['isPending'] ?? false) ? 'بله' : 'خیر' }}</p>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- نمودارها --}}
                <div class="grid gap-4 lg:grid-cols-3">
                    <div wire:key="sitemap-chart-growth-{{ $projectId }}" class="rounded-lg border border-gray-200 p-3 dark:border-white/10 lg:col-span-1">
                        <p class="mb-2 text-xs font-medium text-gray-500 dark:text-gray-400">روند رشد تعداد صفحات (۶ ماه اخیر)</p>
                        <canvas id="sitemap-growth-chart" height="180"></canvas>
                        <script>
                            (function () {
                                var canvas = document.getElementById('sitemap-growth-chart');

                                if (! canvas || ! window.Chart) {
                                    return;
                                }

                                var series = @json($this->growthSeries());

                                new Chart(canvas, {
                                    type: 'line',
                                    data: {
                                        labels: series.map(function (row) { return row.label; }),
                                        datasets: [{
                                            label: 'تعداد صفحات جدید',
                                            data: series.map(function (row) { return row.count; }),
                                            borderColor: '#9c6b1f',
                                            backgroundColor: 'rgba(156, 107, 31, 0.15)',
                                            tension: 0.3,
                                            fill: true,
                                        }],
                                    },
                                    options: {
                                        plugins: { legend: { display: false } },
                                        scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
                                    },
                                });
                            })();
                        </script>
                    </div>

                    <div wire:key="sitemap-chart-content-type-{{ $projectId }}" class="rounded-lg border border-gray-200 p-3 dark:border-white/10 lg:col-span-1">
                        <p class="mb-2 text-xs font-medium text-gray-500 dark:text-gray-400">توزیع نوع محتوا</p>
                        <canvas id="sitemap-content-type-chart" height="180"></canvas>
                        <script>
                            (function () {
                                var canvas = document.getElementById('sitemap-content-type-chart');

                                if (! canvas || ! window.Chart) {
                                    return;
                                }

                                var distribution = @json($overview['content_types']);
                                var labels = Object.keys(distribution);

                                new Chart(canvas, {
                                    type: 'doughnut',
                                    data: {
                                        labels: labels,
                                        datasets: [{
                                            data: labels.map(function (label) { return distribution[label]; }),
                                            backgroundColor: ['#9c6b1f', '#57534e', '#15803d', '#b91c1c', '#2563eb', '#a855f7'],
                                        }],
                                    },
                                    options: {
                                        plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } } },
                                    },
                                });
                            })();
                        </script>
                    </div>

                    <div wire:key="sitemap-chart-lastmod-{{ $projectId }}" class="rounded-lg border border-gray-200 p-3 dark:border-white/10 lg:col-span-1">
                        <p class="mb-2 text-xs font-medium text-gray-500 dark:text-gray-400">باکت‌های lastmod</p>
                        <canvas id="sitemap-lastmod-chart" height="180"></canvas>
                        <script>
                            (function () {
                                var canvas = document.getElementById('sitemap-lastmod-chart');

                                if (! canvas || ! window.Chart) {
                                    return;
                                }

                                var buckets = @json($overview['lastmod_buckets']);
                                var labels = Object.keys(buckets);

                                new Chart(canvas, {
                                    type: 'bar',
                                    data: {
                                        labels: labels,
                                        datasets: [{
                                            data: labels.map(function (label) { return buckets[label]; }),
                                            backgroundColor: '#9c6b1f',
                                        }],
                                    },
                                    options: {
                                        plugins: { legend: { display: false } },
                                        scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
                                    },
                                });
                            })();
                        </script>
                    </div>
                </div>

                {{-- جدول صفحات --}}
                <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-white/10">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 bg-gray-50 text-gray-500 dark:border-white/10 dark:bg-white/5 dark:text-gray-400">
                                <th class="px-3 py-2 text-right font-medium">عنوان / آدرس</th>
                                <th class="px-3 py-2 text-right font-medium">نوع محتوا</th>
                                <th class="px-3 py-2 text-right font-medium">lastmod</th>
                                <th class="px-3 py-2 text-right font-medium">کلیک (۲۸ روز)</th>
                                <th class="px-3 py-2 text-right font-medium">ایمپرشن (۲۸ روز)</th>
                                <th class="px-3 py-2 text-right font-medium">میانگین رتبه</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                            @foreach ($pages->take(200) as $row)
                                <tr>
                                    <td class="px-3 py-2">
                                        <a href="{{ $row['entry']->url }}" target="_blank" rel="noopener" class="text-primary-600 hover:underline">{{ $row['entry']->title ?? '(بدون عنوان)' }}</a>
                                        <div class="max-w-md truncate text-xs text-gray-400" dir="ltr">{{ $row['entry']->url }}</div>
                                    </td>
                                    <td class="px-3 py-2 text-xs text-gray-500 dark:text-gray-400">{{ $row['content_type'] }}</td>
                                    <td class="px-3 py-2 text-xs text-gray-500 dark:text-gray-400">{{ $row['entry']->lastmod?->translatedFormat('Y/m/d') ?? '—' }}</td>
                                    <td class="px-3 py-2 text-xs text-gray-500 dark:text-gray-400" dir="ltr">{{ $row['clicks'] !== null ? number_format($row['clicks']) : '—' }}</td>
                                    <td class="px-3 py-2 text-xs text-gray-500 dark:text-gray-400" dir="ltr">{{ $row['impressions'] !== null ? number_format($row['impressions']) : '—' }}</td>
                                    <td class="px-3 py-2 text-xs text-gray-500 dark:text-gray-400" dir="ltr">{{ $row['position'] !== null ? $row['position'] : '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($pages->count() > 200)
                    <p class="text-xs text-gray-400">فقط ۲۰۰ صفحه‌ی اول نشان داده می‌شود (از {{ number_format($pages->count()) }} صفحه).</p>
                @endif
            @endif
        @endif
    </div>
</x-filament-panels::page>
