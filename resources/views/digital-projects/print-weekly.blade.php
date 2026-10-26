<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>گزارش پروژه‌های دیجیتال</title>
    @fonts('vazirmatn')
    @include('digital-projects._print-styles')
</head>
<body>
    <div class="toolbar"><button type="button" onclick="window.print()">چاپ / دانلود PDF</button></div>

    <div class="sheet">
        <div class="letterhead">
            <div>
                <p class="app-name">{{ config('app.name') }}</p>
                <h1 class="report-title">گزارش پروژه‌های دیجیتال — {{ \Illuminate\Support\Number::toPersian($days) }} روز گذشته</h1>
            </div>
            <div class="meta">
                از {{ $since->toJalali('Y/m/d') }} تا {{ now()->toJalali('Y/m/d') }}<br>
                تهیه‌کننده: {{ $generatedBy }}<br>
                تعداد گزارش: {{ \Illuminate\Support\Number::toPersian($totalLogs) }}
            </div>
        </div>

        @forelse ($projects as $project)
            <section class="project">
                <h3>{{ $project->name }} <span class="tag">{{ $project->channel->getLabel() }}</span> <span class="tag">{{ $project->status->getLabel() }}</span></h3>
                <p class="muted" style="margin:0">مسئول: {{ $project->owner?->name ?? '—' }}</p>

                @foreach ($project->statusChanges as $change)
                    <p style="margin:.25rem 0">تغییر وضعیت در {{ $change->created_at->toJalali('Y/m/d') }}: {{ $change->from_status?->getLabel() ?? '—' }} ← {{ $change->to_status->getLabel() }}{{ $change->note ? ' ('.$change->note.')' : '' }}</p>
                @endforeach

                @foreach ($kinds as $kind)
                    @php $lines = $project->logs->where('kind', $kind); @endphp
                    @if ($lines->isNotEmpty())
                        <strong>{{ $kind->getLabel() }}</strong>
                        <ul class="lines">
                            @foreach ($lines as $line)
                                <li>{{ $line->logged_on->toJalali('Y/m/d') }} — {{ $line->body }}</li>
                            @endforeach
                        </ul>
                    @endif
                @endforeach

                @if ($project->logs->isEmpty())
                    <p class="muted" style="margin:.25rem 0">در این بازه گزارشی ثبت نشده است.</p>
                @endif

                @if ($project->actions->isNotEmpty())
                    <strong>اقدام‌های باز</strong>
                    <ul class="lines">
                        @foreach ($project->actions as $action)
                            <li @class(['late' => $action->isOverdue()])>{{ $action->title }}{{ $action->due_on ? ' — مهلت '.$action->due_on->toJalali('Y/m/d') : '' }}{{ $action->isOverdue() ? ' (از مهلت گذشته)' : '' }}</li>
                        @endforeach
                    </ul>
                @endif
            </section>
        @empty
            <p class="muted">پروژه‌ای ثبت نشده است.</p>
        @endforelse

        <footer class="doc-footer">این گزارش توسط {{ config('app.name') }} تولید شده است — {{ now()->toJalali('Y/m/d H:i') }}</footer>
    </div>
</body>
</html>
