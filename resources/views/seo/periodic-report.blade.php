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
