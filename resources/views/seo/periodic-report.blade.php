<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>گزارش دوره‌ای سئو — {{ $project->name }}</title>
    {{-- Self-hosted via Vite's font-bundling, not Google Fonts - see
        resources/views/reports/print.blade.php's own doc comment. --}}
    @fonts('vazirmatn')
    @include('filament.components.chartjs-assets')
    <style>
        :root {
            color-scheme: light;
            --ink: #1c1917;
            --ink-soft: #57534e;
            --ink-faint: #a8a29e;
            --line: #e7e5e4;
            --gold: #9c6b1f;
            --paper: #f4f4f3;
            --success: #15803d;
            --success-bg: #dcfce7;
            --danger: #b91c1c;
            --danger-bg: #fee2e2;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 2rem 1.25rem;
            background: var(--paper);
            font-family: 'Vazirmatn', Tahoma, 'B Nazanin', Arial, sans-serif;
            color: var(--ink);
        }

        .toolbar {
            max-width: 64rem;
            margin: 0 auto 1rem;
            display: flex;
            justify-content: flex-end;
        }

        .toolbar button {
            font-family: inherit;
            font-size: .875rem;
            font-weight: 500;
            border-radius: .5rem;
            padding: .55rem 1.1rem;
            border: 1px solid var(--gold);
            background: var(--gold);
            color: #fff;
            cursor: pointer;
        }

        .sheet {
            max-width: 64rem;
            margin: 0 auto;
            background: #fff;
            border-radius: .75rem;
            box-shadow: 0 1px 3px rgb(0 0 0 / .1);
            padding: 2.5rem 2.5rem;
        }

        .letterhead {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 1rem;
            padding-bottom: 1.25rem;
            border-bottom: 2px solid var(--ink);
            margin-bottom: 1.5rem;
        }

        .letterhead .app-name {
            font-size: 1.05rem;
            font-weight: 700;
            margin: 0 0 .3rem;
            color: var(--ink-soft);
        }

        h1.report-title {
            font-size: 1.25rem;
            font-weight: 800;
            margin: 0;
        }

        .letterhead .meta {
            flex-shrink: 0;
            text-align: left;
            font-size: .75rem;
            color: var(--ink-faint);
            line-height: 1.8;
            white-space: nowrap;
        }

        h2.section-title {
            font-size: 1rem;
            font-weight: 700;
            margin: 2rem 0 .75rem;
        }

        h2.section-title:first-of-type {
            margin-top: 0;
        }

        .stat-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: .75rem;
        }

        .stat-tile {
            background: #fafaf9;
            border: 1px solid var(--line);
            border-radius: .5rem;
            padding: .75rem .9rem;
        }

        .stat-tile .num {
            font-size: 1.25rem;
            font-weight: 800;
        }

        .stat-tile .label {
            font-size: .7rem;
            color: var(--ink-faint);
            margin-top: .2rem;
        }

        .change-pill {
            display: inline-block;
            font-size: .7rem;
            font-weight: 700;
            border-radius: 999px;
            padding: .1rem .5rem;
            margin-right: .35rem;
        }

        .change-pill.positive {
            color: var(--success);
            background: var(--success-bg);
        }

        .change-pill.negative {
            color: var(--danger);
            background: var(--danger-bg);
        }

        table.report {
            width: 100%;
            border-collapse: collapse;
            font-size: .85rem;
        }

        table.report th, table.report td {
            border-bottom: 1px solid var(--line);
            padding: .6rem .5rem;
            text-align: right;
            vertical-align: top;
        }

        table.report th {
            font-size: .75rem;
            font-weight: 700;
            color: var(--ink-soft);
            background: #fafaf9;
            white-space: nowrap;
        }

        .empty {
            padding: 1.5rem 0;
            text-align: center;
            color: var(--ink-faint);
            font-size: .85rem;
        }

        .narrative {
            background: var(--paper);
            border: 1px solid var(--line);
            border-radius: .5rem;
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
            font-size: .9rem;
            line-height: 1.9;
        }

        .chart-wrap {
            margin-bottom: 1.5rem;
        }

        .chart-label {
            font-size: .8rem;
            font-weight: 700;
            color: var(--ink-soft);
            margin: 1.5rem 0 .6rem;
        }

        .chart-label:first-of-type {
            margin-top: 0;
        }

        .chart-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: .75rem;
            margin-bottom: 1.5rem;
        }

        .chart-grid .mini-chart {
            background: #fafaf9;
            border: 1px solid var(--line);
            border-radius: .5rem;
            padding: .75rem;
        }

        .chart-grid .mini-chart .mini-chart-title {
            font-size: .72rem;
            color: var(--ink-soft);
            margin-bottom: .5rem;
            text-align: center;
        }

        .movers-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .movers-grid h3 {
            font-size: .8rem;
            font-weight: 700;
            margin: 0 0 .5rem;
            color: var(--ink-soft);
        }

        .badge-pill {
            display: inline-block;
            font-size: .68rem;
            font-weight: 700;
            border-radius: 999px;
            padding: .1rem .5rem;
            white-space: nowrap;
        }

        .badge-pill.tone-danger {
            color: var(--danger);
            background: var(--danger-bg);
        }

        .badge-pill.tone-warning {
            color: #92400e;
            background: #fef3c7;
        }

        footer.doc-footer {
            margin-top: 1.5rem;
            padding-top: 1rem;
            border-top: 1px solid var(--line);
            font-size: .7rem;
            color: var(--ink-faint);
            text-align: center;
        }

        @media (max-width: 640px) {
            .sheet {
                padding: 1.5rem 1.25rem;
                overflow-x: auto;
            }

            .letterhead {
                flex-direction: column;
            }

            .letterhead .meta {
                text-align: right;
            }

            .stat-row {
                grid-template-columns: repeat(2, 1fr);
            }

            .chart-grid, .movers-grid {
                grid-template-columns: 1fr;
            }
        }

        @media print {
            body {
                background: #fff;
                padding: 0;
            }

            .toolbar {
                display: none;
            }

            .sheet {
                box-shadow: none;
                border-radius: 0;
                padding: 0;
                max-width: none;
            }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" onclick="window.print()">چاپ / دانلود PDF</button>
    </div>

    <div class="sheet">
        <div class="letterhead">
            <div>
                <p class="app-name">{{ config('app.name') }} — مرکز سئو</p>
                <h1 class="report-title">گزارش دوره‌ای عملکرد سئو: {{ $project->name }}</h1>
            </div>
            <div class="meta">
                بازهٔ گزارش: {{ $periodStart->toJalali('Y/m/d') }} تا {{ $periodEnd->toJalali('Y/m/d') }}<br>
                تاریخ تهیه: {{ now()->toJalali('Y/m/d H:i') }}
                @if ($dataAvailableThrough)
                    <br>داده‌ی گوگل تا {{ $dataAvailableThrough->toJalali('Y/m/d') }} در دسترس بوده (تأخیر گزارش‌دهی سرچ کنسول)
                @endif
            </div>
        </div>

        @if ($narrative)
            <div class="narrative">{{ $narrative }}</div>
        @endif

        @if (count($dailySeries) > 1)
            <p class="chart-label">روند کلیک روزانه</p>
            <div class="chart-wrap">
                <canvas id="periodic-report-clicks-chart" height="220"></canvas>
                <script>
                    (function () {
                        var canvas = document.getElementById('periodic-report-clicks-chart');

                        if (! canvas || ! window.Chart) {
                            return;
                        }

                        var series = @json($dailySeries);

                        new Chart(canvas, {
                            type: 'line',
                            data: {
                                labels: series.map(function (row) { return row.date; }),
                                datasets: [{
                                    label: 'کلیک روزانه',
                                    data: series.map(function (row) { return row.clicks; }),
                                    borderColor: '#9c6b1f',
                                    backgroundColor: 'rgba(156, 107, 31, 0.15)',
                                    tension: 0.3,
                                    fill: true,
                                    pointRadius: 0,
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
        @endif

        @if (count($dailyQualitySeries) > 1)
            <p class="chart-label">روند نرخ کلیک (CTR) و میانگین رتبه</p>
            <div class="chart-wrap">
                <canvas id="periodic-report-quality-chart" height="240"></canvas>
                <script>
                    (function () {
                        var canvas = document.getElementById('periodic-report-quality-chart');

                        if (! canvas || ! window.Chart) {
                            return;
                        }

                        var series = @json($dailyQualitySeries);

                        new Chart(canvas, {
                            type: 'line',
                            data: {
                                labels: series.map(function (row) { return row.date; }),
                                datasets: [
                                    {
                                        label: 'نرخ کلیک (CTR٪)',
                                        data: series.map(function (row) { return row.ctr; }),
                                        borderColor: '#9c6b1f',
                                        backgroundColor: 'transparent',
                                        tension: 0.3,
                                        pointRadius: 0,
                                        yAxisID: 'ctr',
                                    },
                                    {
                                        label: 'میانگین رتبه',
                                        data: series.map(function (row) { return row.position; }),
                                        borderColor: '#57534e',
                                        backgroundColor: 'transparent',
                                        borderDash: [4, 3],
                                        tension: 0.3,
                                        pointRadius: 0,
                                        yAxisID: 'position',
                                    },
                                ],
                            },
                            options: {
                                plugins: { legend: { display: true, position: 'top', labels: { boxWidth: 10, font: { size: 10 } } } },
                                scales: {
                                    ctr: { position: 'left', beginAtZero: true, ticks: { callback: function (v) { return v + '٪'; } } },
                                    position: { position: 'right', reverse: true, grid: { drawOnChartArea: false } },
                                },
                            },
                        });
                    })();
                </script>
            </div>
        @endif

        <h2 class="section-title">عملکرد جست‌وجوی ارگانیک</h2>
        <div class="stat-row">
            <div class="stat-tile">
                <div class="num">
                    {{ \Illuminate\Support\Number::toPersian(number_format($search['clicks'])) }}
                    @if ($search['percent_change'] !== null)
                        <span class="change-pill {{ $search['percent_change'] >= 0 ? 'positive' : 'negative' }}">
                            {{ $search['percent_change'] >= 0 ? '+' : '' }}{{ \Illuminate\Support\Number::toPersian($search['percent_change']) }}٪
                        </span>
                    @endif
                </div>
                <div class="label">کلیک (نسبت به دورهٔ قبل)</div>
            </div>
            <div class="stat-tile">
                <div class="num">{{ \Illuminate\Support\Number::toPersian(number_format($search['impressions'])) }}</div>
                <div class="label">ایمپرشن</div>
            </div>
            <div class="stat-tile">
                <div class="num">{{ $search['avg_position'] !== null ? \Illuminate\Support\Number::toPersian($search['avg_position']) : '—' }}</div>
                <div class="label">میانگین رتبه</div>
            </div>
            <div class="stat-tile">
                <div class="num">{{ $technicalAudit ? \Illuminate\Support\Number::toPersian($technicalAudit->score).'/۱۰۰' : '—' }}</div>
                <div class="label">آخرین امتیاز ممیزی فنی</div>
            </div>
        </div>

        <p class="chart-label">مقایسه با دورهٔ قبل</p>
        <div class="chart-grid">
            <div class="mini-chart">
                <div class="mini-chart-title">کلیک</div>
                <canvas id="compare-clicks" height="140"></canvas>
            </div>
            <div class="mini-chart">
                <div class="mini-chart-title">ایمپرشن</div>
                <canvas id="compare-impressions" height="140"></canvas>
            </div>
            <div class="mini-chart">
                <div class="mini-chart-title">میانگین رتبه</div>
                <canvas id="compare-position" height="140"></canvas>
            </div>
        </div>
        <script>
            (function () {
                if (! window.Chart) {
                    return;
                }

                [
                    { id: 'compare-clicks', previous: {{ $search['previous_clicks'] }}, current: {{ $search['clicks'] }} },
                    { id: 'compare-impressions', previous: {{ $search['previous_impressions'] }}, current: {{ $search['impressions'] }} },
                    { id: 'compare-position', previous: {{ $search['previous_avg_position'] ?? 0 }}, current: {{ $search['avg_position'] ?? 0 }} },
                ].forEach(function (c) {
                    var canvas = document.getElementById(c.id);

                    if (! canvas) {
                        return;
                    }

                    new Chart(canvas, {
                        type: 'bar',
                        data: {
                            labels: ['دورهٔ قبل', 'این دوره'],
                            datasets: [{
                                data: [c.previous, c.current],
                                backgroundColor: ['#d6d3d1', '#9c6b1f'],
                            }],
                        },
                        options: {
                            plugins: { legend: { display: false } },
                            scales: { y: { beginAtZero: true } },
                        },
                    });
                });
            })();
        </script>

        @if (count($deviceBreakdown) > 0)
            <p class="chart-label" style="text-align: center;">توزیع کلیک بر اساس دستگاه</p>
            <div class="chart-wrap" style="max-width: 20rem; margin-right: auto; margin-left: auto;">
                <canvas id="periodic-report-device-chart" height="200"></canvas>
                <script>
                    (function () {
                        var canvas = document.getElementById('periodic-report-device-chart');

                        if (! canvas || ! window.Chart) {
                            return;
                        }

                        var breakdown = @json($deviceBreakdown);
                        var labels = { DESKTOP: 'دسکتاپ', MOBILE: 'موبایل', TABLET: 'تبلت' };

                        new Chart(canvas, {
                            type: 'doughnut',
                            data: {
                                labels: Object.keys(breakdown).map(function (k) { return labels[k] || k; }),
                                datasets: [{
                                    data: Object.values(breakdown),
                                    backgroundColor: ['#9c6b1f', '#57534e', '#d6d3d1'],
                                }],
                            },
                            options: {
                                plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 10 } } } },
                            },
                        });
                    })();
                </script>
            </div>
        @endif

        <h2 class="section-title">فرصت‌های سئو</h2>
        <div class="stat-row">
            <div class="stat-tile">
                <div class="num">{{ \Illuminate\Support\Number::toPersian($opportunities['detected']) }}</div>
                <div class="label">فرصت شناسایی‌شده</div>
            </div>
            <div class="stat-tile">
                <div class="num">{{ \Illuminate\Support\Number::toPersian($opportunities['resolved']) }}</div>
                <div class="label">فرصت برطرف‌شده</div>
            </div>
            <div class="stat-tile">
                <div class="num">{{ \Illuminate\Support\Number::toPersian($opportunities['improved']) }}</div>
                <div class="label">اقدام موفق (بهبود واقعی)</div>
            </div>
            <div class="stat-tile">
                <div class="num">{{ \Illuminate\Support\Number::toPersian($contentPublishedCount) }}</div>
                <div class="label">محتوای منتشرشده</div>
            </div>
        </div>

        <h2 class="section-title">اقدامات پیشنهادی</h2>
        @if ($actionItems->isEmpty())
            <p class="empty">در حال حاضر هیچ موردی در صف اقدام نیست.</p>
        @else
            <table class="report">
                <thead>
                    <tr>
                        <th>منبع</th>
                        <th>عنوان</th>
                        <th>جزئیات</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($actionItems as $item)
                        <tr>
                            <td>
                                <span class="badge-pill {{ $item->source === 'technical_issue' ? 'tone-danger' : 'tone-warning' }}">
                                    {{ $item->source === 'technical_issue' ? 'مشکل تکنیکال' : 'فرصت سئو' }}
                                </span>
                            </td>
                            <td>{{ $item->title }}</td>
                            <td>{{ $item->detail ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        @if ($technicalIssues->isNotEmpty())
            <h2 class="section-title">مشکلات فنی شناسایی‌شده</h2>
            <table class="report">
                <thead>
                    <tr>
                        <th>شدت</th>
                        <th>نوع مشکل</th>
                        <th>آدرس</th>
                        <th>جزئیات</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($technicalIssues as $issue)
                        <tr>
                            <td>
                                <span class="badge-pill {{ $issue->severity->value === 'critical' ? 'tone-danger' : 'tone-warning' }}">
                                    {{ $issue->severity->getLabel() }}
                                </span>
                            </td>
                            <td>{{ $issue->type->getLabel() }}</td>
                            <td style="word-break: break-all;">{{ $issue->url ?? '—' }}</td>
                            <td>{{ $issue->details ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <h2 class="section-title">پرکلیک‌ترین کلمات کلیدی (رشد و افت این دوره)</h2>
        @if (empty($topQueries['gainers']) && empty($topQueries['losers']))
            <p class="empty">در این بازه تغییر قابل‌توجهی در کلیک کلمات کلیدی ثبت نشده است.</p>
        @else
            <div class="movers-grid">
                <div>
                    <h3>بیشترین رشد</h3>
                    @if (empty($topQueries['gainers']))
                        <p class="empty">موردی یافت نشد.</p>
                    @else
                        <table class="report">
                            <thead><tr><th>کلمهٔ کلیدی</th><th>تغییر</th></tr></thead>
                            <tbody>
                                @foreach ($topQueries['gainers'] as $row)
                                    <tr>
                                        <td>{{ $row['label'] }}</td>
                                        <td><span class="change-pill positive">+{{ \Illuminate\Support\Number::toPersian($row['delta']) }}</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
                <div>
                    <h3>بیشترین افت</h3>
                    @if (empty($topQueries['losers']))
                        <p class="empty">موردی یافت نشد.</p>
                    @else
                        <table class="report">
                            <thead><tr><th>کلمهٔ کلیدی</th><th>تغییر</th></tr></thead>
                            <tbody>
                                @foreach ($topQueries['losers'] as $row)
                                    <tr>
                                        <td>{{ $row['label'] }}</td>
                                        <td><span class="change-pill negative">{{ \Illuminate\Support\Number::toPersian($row['delta']) }}</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>
        @endif

        <h2 class="section-title">پربازدیدترین صفحات (رشد و افت این دوره)</h2>
        @if (empty($topPages['gainers']) && empty($topPages['losers']))
            <p class="empty">در این بازه تغییر قابل‌توجهی در کلیک صفحات ثبت نشده است.</p>
        @else
            <div class="movers-grid">
                <div>
                    <h3>بیشترین رشد</h3>
                    @if (empty($topPages['gainers']))
                        <p class="empty">موردی یافت نشد.</p>
                    @else
                        <table class="report">
                            <thead><tr><th>صفحه</th><th>تغییر</th></tr></thead>
                            <tbody>
                                @foreach ($topPages['gainers'] as $row)
                                    <tr>
                                        <td style="word-break: break-all;">{{ $row['label'] }}</td>
                                        <td><span class="change-pill positive">+{{ \Illuminate\Support\Number::toPersian($row['delta']) }}</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
                <div>
                    <h3>بیشترین افت</h3>
                    @if (empty($topPages['losers']))
                        <p class="empty">موردی یافت نشد.</p>
                    @else
                        <table class="report">
                            <thead><tr><th>صفحه</th><th>تغییر</th></tr></thead>
                            <tbody>
                                @foreach ($topPages['losers'] as $row)
                                    <tr>
                                        <td style="word-break: break-all;">{{ $row['label'] }}</td>
                                        <td><span class="change-pill negative">{{ \Illuminate\Support\Number::toPersian($row['delta']) }}</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>
        @endif

        <h2 class="section-title">روند رتبهٔ کلمات کلیدی ترکرشده</h2>
        @if ($rankHighlights->isEmpty())
            <p class="empty">در این بازه هیچ بررسی رتبه‌ای ثبت نشده است.</p>
        @else
            <table class="report">
                <thead>
                    <tr>
                        <th>کلمهٔ کلیدی</th>
                        <th>رتبهٔ ابتدای دوره</th>
                        <th>رتبهٔ انتهای دوره</th>
                        <th>تغییر</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rankHighlights->take(10) as $row)
                        <tr>
                            <td>{{ $row['keyword'] }}</td>
                            <td>{{ $row['from'] ?? 'خارج از ۱۰ نتیجهٔ اول' }}</td>
                            <td>{{ $row['to'] ?? 'خارج از ۱۰ نتیجهٔ اول' }}</td>
                            <td>
                                @if ($row['delta'] === null)
                                    —
                                @else
                                    <span class="change-pill {{ $row['delta'] >= 0 ? 'positive' : 'negative' }}">
                                        {{ $row['delta'] >= 0 ? '+' : '' }}{{ \Illuminate\Support\Number::toPersian($row['delta']) }}
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <footer class="doc-footer">این گزارش به‌صورت خودکار توسط {{ config('app.name') }} تولید شده است — {{ now()->toJalali('Y/m/d H:i') }}</footer>
    </div>
</body>
</html>
