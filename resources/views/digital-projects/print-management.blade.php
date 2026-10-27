<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>وضعیت جامع مدیا</title>
    @fonts('vazirmatn')
    <style>
        :root { color-scheme: light; --blue: #44609a; --green: #3ddc4a; --line: #1c1917; --soft: #e7e5e4; --band: #f6f7fb; }
        * { box-sizing: border-box; }
        @page { size: A4 landscape; margin: 8mm; }
        body { margin: 0; padding: 1.5rem 1rem; background: #f4f4f3; font-family: 'Vazirmatn', Tahoma, 'B Nazanin', Arial, sans-serif; color: #1c1917; }
        .toolbar { max-width: 72rem; margin: 0 auto 1rem; display: flex; justify-content: flex-end; gap: .5rem; }
        .toolbar button, .toolbar a { font-family: inherit; font-size: .875rem; border-radius: .5rem; padding: .5rem 1rem; border: 1px solid #9c6b1f; background: #9c6b1f; color: #fff; cursor: pointer; text-decoration: none; }
        .toolbar a { background: #fff; color: #9c6b1f; }
        .sheet { max-width: 72rem; margin: 0 auto; background: #fff; padding: 1rem; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; font-size: 11px; }
        th, td { border: 1px solid var(--line); text-align: center; vertical-align: middle; padding: 3px 2px; height: 22px; }
        thead { display: table-header-group; }
        tr { break-inside: avoid; page-break-inside: avoid; }
        th.title { background: var(--blue); color: #fff; font-size: 14px; padding: 6px; }
        th.group { background: var(--green); font-size: 12px; }
        th.col { background: #fff; font-weight: 600; font-size: 10px; }
        th.corner { background: var(--blue); color: #fff; }
        td.subject { background: var(--band); font-weight: 600; width: 34px; }
        td.subject span { writing-mode: vertical-rl; display: inline-block; white-space: nowrap; }
        td.name { width: 150px; font-weight: 500; }
        tbody tr:nth-child(even) td:not(.subject) { background: var(--band); }
        tr.channel-first td { border-top: 2px solid var(--line); }
        .meta { margin-top: .5rem; font-size: 10px; color: #78716c; text-align: left; }
        @media print { body { background: #fff; padding: 0; } .toolbar { display: none; } .sheet { padding: 0; max-width: none; overflow: visible; } }
    </style>
</head>
<body>
    <div class="toolbar">
        <a href="{{ $excelUrl }}">خروجی اکسل</a>
        <button type="button" onclick="window.print()">چاپ / دانلود PDF (A4 افقی)</button>
    </div>

    <div class="sheet">
        <table>
            <thead>
                <tr>
                    <th class="title" colspan="{{ 2 + collect($columnGroups)->sum(fn ($group) => count($group['columns'])) }}">
                        وضعیت جامع مدیا — {{ \Illuminate\Support\Number::toPersian(\Morilog\Jalali\Jalalian::fromCarbon($report['end'])->format('%d %B %Y')) }}
                    </th>
                </tr>
                <tr>
                    <th class="corner" rowspan="2" style="width:34px">موضوع</th>
                    <th class="corner" rowspan="2" style="width:150px">نام پیج</th>
                    @foreach ($columnGroups as $group)
                        <th class="group" colspan="{{ count($group['columns']) }}">{{ $group['title'] }}</th>
                    @endforeach
                </tr>
                <tr>
                    @foreach ($columnGroups as $group)
                        @foreach ($group['columns'] as $column)
                            <th class="col">{{ $column['label'] }}</th>
                        @endforeach
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($report['groups'] as $group)
                    @foreach ($group['rows'] as $row)
                        <tr @class(['channel-first' => $loop->first])>
                            @if ($loop->first)
                                <td class="subject" rowspan="{{ count($group['rows']) }}"><span>{{ $group['channel']->getLabel() }}</span></td>
                            @endif
                            <td class="name">{{ $row['project']->name }}</td>
                            @foreach ($columnGroups as $columnGroup)
                                @foreach ($columnGroup['columns'] as $column)
                                    <td>{{ \App\Domain\DigitalProjects\DigitalProjectManagementReport::format($column['key'], $row['cells'][$column['key']]) }}</td>
                                @endforeach
                            @endforeach
                        </tr>
                    @endforeach
                @empty
                    <tr><td colspan="14">پروژه‌ای برای نمایش نیست.</td></tr>
                @endforelse
            </tbody>
        </table>
        <p class="meta">
            هفتهٔ {{ \Illuminate\Support\Number::toPersian(\Morilog\Jalali\Jalalian::fromCarbon($report['start'])->format('Y/m/d')) }}
            تا {{ \Illuminate\Support\Number::toPersian(\Morilog\Jalali\Jalalian::fromCarbon($report['end'])->format('Y/m/d')) }}
            — تهیه‌کننده: {{ $generatedBy }}
        </p>
    </div>
</body>
</html>
