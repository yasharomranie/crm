<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>برگه‌ی نظافت {{ $date->toJalali('Y/m/d') }}</title>
    <style>
        @page { size: A4; margin: 14mm; }
        * { box-sizing: border-box; }
        body { margin: 0; padding: 16px; font-family: Vazirmatn, Tahoma, sans-serif; color: #1c1917; background: #f4f4f5; }
        .sheet { max-width: 800px; margin: 0 auto; background: #fff; padding: 24px; border: 1px solid #e4e4e7; }
        .bar { max-width: 800px; margin: 0 auto 12px; }
        .bar button { padding: .4rem 1rem; border: 0; border-radius: .5rem; background: #16a34a; color: #fff; cursor: pointer; font: inherit; }
        h1 { margin: 0 0 4px; font-size: 1.25rem; }
        h2 { margin: 20px 0 8px; font-size: 1rem; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 6px 8px; border-bottom: 1px solid #e4e4e7; text-align: right; vertical-align: top; }
        td.tick { width: 2.5rem; text-align: center; }
        .box { display: inline-block; width: 1rem; height: 1rem; border: 1.5px solid #1c1917; border-radius: 3px; }
        .muted { color: #57534e; }
        @media print { body { background: #fff; padding: 0; } .bar { display: none; } .sheet { border: 0; padding: 0; } }
    </style>
</head>
<body>
@php
    $number = fn ($value) => \Illuminate\Support\Number::toPersian($value);
    $sections = [
        ['title' => 'بعد از خروج: نظافت کامل', 'rows' => $departures, 'empty' => 'خروجی‌ای نیست.', 'kind' => 'departure'],
        ['title' => 'آماده‌سازی برای ورود', 'rows' => $arrivals, 'empty' => 'ورودی‌ای نیست.', 'kind' => 'arrival'],
        ['title' => 'مهمان مقیم: سرویس روزانه', 'rows' => $stayovers, 'empty' => 'مهمان مقیمی نیست.', 'kind' => 'stayover'],
    ];
@endphp
<div class="bar"><button type="button" onclick="window.print()">چاپ</button></div>
<div class="sheet">
    <h1>برگه‌ی نظافت روزانه</h1>
    <div class="muted">{{ $date->toJalali('l Y/m/d') }}</div>

    @foreach ($sections as $section)
        <h2>{{ $section['title'] }} ({{ $number($section['rows']->count()) }})</h2>
        @if ($section['rows']->isEmpty())
            <div class="muted">{{ $section['empty'] }}</div>
        @else
            <table>
                <thead>
                    <tr>
                        <th class="tick">✓</th>
                        <th>واحد</th>
                        <th>مهمان</th>
                        <th>توضیح</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($section['rows'] as $booking)
                        <tr>
                            <td class="tick"><span class="box"></span></td>
                            <td>{{ $booking->room->name }} <span class="muted">({{ $booking->room->roomType->name }})</span></td>
                            <td>{{ $booking->guest->name }}</td>
                            <td>
                                @if ($section['kind'] === 'arrival')
                                    {{ $number($booking->adults_count + $booking->children_count) }} نفر
                                    @if ($booking->precheckin?->arrival_time) · رسیدن حدود {{ $number($booking->precheckin->arrival_time) }} @endif
                                    @if ($booking->precheckin?->requests) <div class="muted">درخواست: {{ $booking->precheckin->requests }}</div> @endif
                                @elseif ($section['kind'] === 'stayover')
                                    خروج {{ $booking->check_out_date->toJalali('Y/m/d') }}
                                @else
                                    ساعت خروج {{ $number($booking->room->roomType->check_out_time) }}
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    @endforeach
</div>
</body>
</html>
