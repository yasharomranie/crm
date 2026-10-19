<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>صورت‌حساب {{ $booking->reference_code }}</title>
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
        th, td { padding: 6px 8px; border-bottom: 1px solid #e4e4e7; text-align: right; }
        td.num, th.num { text-align: left; white-space: nowrap; }
        .meta { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 4px 24px; color: #57534e; margin-bottom: 8px; }
        .total td { font-weight: 700; border-top: 2px solid #1c1917; }
        @media print { body { background: #fff; padding: 0; } .bar { display: none; } .sheet { border: 0; padding: 0; } }
    </style>
</head>
<body>
@php
    $money = fn (int|float $amount) => \Illuminate\Support\Number::toPersian(number_format((int) $amount)).' تومان';
@endphp
<div class="bar"><button type="button" onclick="window.print()">چاپ</button></div>
<div class="sheet">
    <h1>صورت‌حساب اقامت</h1>
    <div class="meta">
        <span>کد رزرو: {{ $booking->reference_code }}</span>
        <span>مهمان: {{ $booking->guest->name }}</span>
        <span>واحد: {{ $booking->room->name }} ({{ $booking->room->roomType->name }})</span>
        <span>ورود: {{ $booking->check_in_date->toJalali('Y/m/d') }}</span>
        <span>خروج: {{ $booking->check_out_date->toJalali('Y/m/d') }}</span>
        <span>تاریخ صدور: {{ now()->toJalali('Y/m/d') }}</span>
    </div>

    <h2>{{ $booking->status === \App\Enums\BookingStatus::Cancelled ? 'اقلام (رزرو لغو شده؛ فقط جریمه‌ی لغو دریافت می‌شود)' : 'اقلام' }}</h2>
    <table>
        <thead><tr><th>شرح</th><th class="num">تعداد</th><th class="num">مبلغ واحد</th><th class="num">جمع</th></tr></thead>
        <tbody>
            @foreach ($booking->charges as $charge)
                <tr>
                    <td>{{ $charge->title }}</td>
                    <td class="num">{{ \Illuminate\Support\Number::toPersian($charge->quantity) }}</td>
                    <td class="num">{{ $money($charge->unit_amount) }}</td>
                    <td class="num">{{ $money($charge->lineTotal()) }}</td>
                </tr>
            @endforeach
            <tr class="total"><td colspan="3">جمع کل صورت‌حساب</td><td class="num">{{ $money($booking->totalAmount()) }}</td></tr>
            @if ($booking->status === \App\Enums\BookingStatus::Cancelled)
                <tr><td colspan="3">جریمه‌ی لغو</td><td class="num">{{ $money($booking->cancellation_fee) }}</td></tr>
            @endif
        </tbody>
    </table>

    <h2>پرداخت‌ها</h2>
    <table>
        <thead><tr><th>تاریخ</th><th>نوع</th><th>روش</th><th class="num">مبلغ</th></tr></thead>
        <tbody>
            @if ((int) $booking->wallet_paid_amount > 0)
                <tr><td>—</td><td>پرداخت</td><td>کیف پول</td><td class="num">{{ $money($booking->wallet_paid_amount) }}</td></tr>
            @endif
            @forelse ($booking->payments as $payment)
                <tr>
                    <td>{{ $payment->paid_at?->toJalali('Y/m/d') }}</td>
                    <td>{{ $payment->kind->getLabel() }}</td>
                    <td>{{ $payment->method->getLabel() }}{{ $payment->reference ? ' - '.$payment->reference : '' }}</td>
                    <td class="num">{{ $payment->kind === \App\Enums\BookingPaymentKind::Refund ? '-' : '' }}{{ $money($payment->amount) }}</td>
                </tr>
            @empty
                @if ((int) $booking->wallet_paid_amount === 0)
                    <tr><td colspan="4">پرداختی ثبت نشده است.</td></tr>
                @endif
            @endforelse
            <tr class="total"><td colspan="3">جمع پرداخت‌شده</td><td class="num">{{ $money($booking->paidAmount()) }}</td></tr>
            <tr class="total"><td colspan="3">مانده‌ی قابل پرداخت</td><td class="num">{{ $money($booking->balanceDue()) }}</td></tr>
        </tbody>
    </table>
</div>
</body>
</html>
