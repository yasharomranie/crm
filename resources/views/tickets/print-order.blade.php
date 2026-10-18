<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>{{ $order->order_number }}</title>
    <style>
        @page { size: {{ $thermal ? '80mm auto' : 'A4' }}; margin: {{ $thermal ? '2mm' : '10mm' }}; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #f4f4f5; font-family: Tahoma, sans-serif; }
        .bar { padding: .75rem 1rem; background: #fff; border-bottom: 1px solid #e4e4e7; display: flex; gap: .75rem; align-items: center; }
        .bar button { padding: .4rem 1rem; border: 0; border-radius: .5rem; background: #16a34a; color: #fff; cursor: pointer; }
        iframe { display: block; width: 100%; border: 0; background: #fff; {{ $thermal ? 'max-width: 76mm;' : '' }} margin: 0 auto; page-break-after: always; break-after: page; }
        @media print { body { background: #fff; } .bar { display: none; } }
    </style>
</head>
<body>
    <div class="bar">
        <button type="button" onclick="window.print()">چاپ</button>
        <span>{{ $order->order_number }} · {{ $tickets->count() }} بلیت</span>
    </div>

    @foreach ($tickets as $ticket)
        <iframe src="{{ route('admission-tickets.print', $ticket) }}" title="{{ $ticket->ticket_number }}" onload="this.style.height = (this.contentDocument.documentElement.scrollHeight + 8) + 'px'; window.__loaded = (window.__loaded || 0) + 1; if (window.__loaded === {{ $tickets->count() }}) { window.dispatchEvent(new Event('tickets-ready')); }"></iframe>
    @endforeach

    @if ($autoPrint)
        <script>
            window.addEventListener('tickets-ready', function () { setTimeout(function () { window.print(); }, 300); });
        </script>
    @endif
</body>
</html>
