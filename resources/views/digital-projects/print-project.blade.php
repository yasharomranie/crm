<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $project->name }}</title>
    @fonts('vazirmatn')
    @include('digital-projects._print-styles')
</head>
<body>
    <div class="toolbar"><button type="button" onclick="window.print()">چاپ / دانلود PDF</button></div>

    <div class="sheet">
        <div class="letterhead">
            <div>
                <p class="app-name">{{ config('app.name') }} — پروژه‌های دیجیتال</p>
                <h1 class="report-title">{{ $project->name }}</h1>
            </div>
            <div class="meta">
                تهیه‌کننده: {{ $generatedBy }}<br>
                تاریخ تهیه: {{ now()->toJalali('Y/m/d H:i') }}
            </div>
        </div>

        <table>
            <tbody>
                <tr><th>نوع</th><td>{{ $project->channel->getLabel() }}</td><th>وضعیت</th><td>{{ $project->status->getLabel() }}</td></tr>
                <tr><th>آدرس یا آیدی</th><td>{{ $project->handle ?: '—' }}</td><th>مسئول</th><td>{{ $project->owner?->name ?? '—' }}</td></tr>
                <tr>
                    <th>بررسی بعدی</th>
                    <td colspan="3" @class(['late' => $project->isReviewOverdue()])>{{ $project->next_review_at ? $project->next_review_at->toJalali('Y/m/d') : '—' }}{{ $project->isReviewOverdue() ? ' (عقب‌افتاده)' : '' }}</td>
                </tr>
            </tbody>
        </table>

        @if (filled($project->description))
            <h2>شرح پروژه</h2>
            <p style="white-space: pre-line">{{ $project->description }}</p>
        @endif

        <h2>اقدام‌ها</h2>
        @if ($actions->isEmpty())
            <p class="muted">اقدامی ثبت نشده است.</p>
        @else
            <table>
                <thead><tr><th>اقدام</th><th>وضعیت</th><th>مسئول</th><th>مهلت</th></tr></thead>
                <tbody>
                    @foreach ($actions as $action)
                        <tr>
                            <td>{{ $action->title }}</td>
                            <td>{{ $action->status->getLabel() }}</td>
                            <td>{{ $action->assignee?->name ?? '—' }}</td>
                            <td @class(['late' => $action->isOverdue()])>{{ $action->due_on ? $action->due_on->toJalali('Y/m/d') : '—' }}{{ $action->isOverdue() ? ' (از مهلت گذشته)' : '' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <h2>آخرین گزارش‌های روزانه</h2>
        @if ($logs->isEmpty())
            <p class="muted">گزارشی ثبت نشده است.</p>
        @else
            <table>
                <thead><tr><th>تاریخ</th><th>نوع</th><th>گزارش</th><th>ثبت‌کننده</th></tr></thead>
                <tbody>
                    @foreach ($logs as $log)
                        <tr>
                            <td>{{ $log->logged_on->toJalali('Y/m/d') }}</td>
                            <td>{{ $log->kind->getLabel() }}</td>
                            <td style="white-space: pre-line">{{ $log->body }}</td>
                            <td>{{ $log->author?->name ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <h2>تاریخچهٔ وضعیت</h2>
        @if ($changes->isEmpty())
            <p class="muted">تغییری ثبت نشده است.</p>
        @else
            <table>
                <thead><tr><th>زمان</th><th>از</th><th>به</th><th>دلیل</th><th>تغییردهنده</th></tr></thead>
                <tbody>
                    @foreach ($changes as $change)
                        <tr>
                            <td>{{ $change->created_at->toJalali('Y/m/d H:i') }}</td>
                            <td>{{ $change->from_status?->getLabel() ?? '—' }}</td>
                            <td>{{ $change->to_status->getLabel() }}</td>
                            <td>{{ $change->note ?: '—' }}</td>
                            <td>{{ $change->author?->name ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <footer class="doc-footer">این گزارش توسط {{ config('app.name') }} تولید شده است — {{ now()->toJalali('Y/m/d H:i') }}</footer>
    </div>
</body>
</html>
