<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>{{ $ticket->ticket_number }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: Tahoma, sans-serif; margin: 0; padding: 1.5rem; background: #f4f4f5; }
        .ticket {
            position: relative;
            margin: 0 auto;
            background: #fff;
            border: 1px solid #d4d4d8;
            border-radius: .75rem;
            overflow: hidden;
            padding: 1.5rem;
        }
        .ticket.has-template {
            width: {{ ($ticket->ticketTemplateVersion->width_mm ?? 100) * 3.78 }}px;
            height: {{ ($ticket->ticketTemplateVersion->height_mm ?? 150) * 3.78 }}px;
        }
        .ticket.default-layout { max-width: 380px; text-align: center; }
        .element { position: absolute; overflow: hidden; }
        h1 { font-size: 1.1rem; margin: .25rem 0; }
        .ticket-number { font-family: monospace; font-size: 1rem; letter-spacing: .05em; margin: .5rem 0; }
        .meta { font-size: .8rem; color: #52525b; margin: .2rem 0; }
        #qrcode { display: flex; justify-content: center; margin: 1rem 0; }
        @media print {
            body { background: #fff; padding: 0; }
            .ticket { border: none; }
        }
    </style>
</head>
<body>
    @php
        $variant = $ticket->ticketVariant;
        $product = $variant->product;
        $version = $ticket->ticketTemplateVersion;

        $variables = [
            'ticket_number' => $ticket->ticket_number,
            'order_number' => $ticket->orderItem->order->order_number ?? '',
            'product_name' => $product->name,
            'ticket_type' => $variant->name,
            'valid_from' => $ticket->valid_from->toJalali('Y/m/d H:i'),
            'valid_until' => $ticket->valid_until->toJalali('Y/m/d H:i'),
            'customer_name' => $ticket->customer_name ?? '',
            'customer_phone' => $ticket->customer_phone ?? '',
            'direction' => $variant->direction?->getLabel() ?? '',
            'usage_count' => (string) $ticket->used_uses,
            'remaining_uses' => (string) $ticket->remaining_uses,
        ];

        $resolve = fn (string $content) => preg_replace_callback(
            '/\{\{\s*([a-z_]+)\s*\}\}/',
            fn ($m) => $variables[$m[1]] ?? $m[0],
            $content,
        );
    @endphp

    @if ($version && $version->elements->isNotEmpty())
        <div class="ticket has-template">
            @foreach ($version->elements as $element)
                @php $settings = $element->settings ?? []; @endphp
                <div
                    class="element"
                    style="
                        left: {{ $element->x }}px; top: {{ $element->y }}px;
                        width: {{ $element->width }}px; height: {{ $element->height }}px;
                        font-size: {{ $settings['font_size'] ?? 14 }}px;
                        font-weight: {{ $settings['font_weight'] ?? 'normal' }};
                        color: {{ $settings['color'] ?? '#111827' }};
                        text-align: {{ $settings['alignment'] ?? 'start' }};
                    "
                >
                    @if ($element->type === 'qr_code')
                        <div id="qrcode"></div>
                    @elseif ($element->type === 'image' && $element->content)
                        <img src="{{ $element->content }}" style="width:100%;height:100%;object-fit:{{ $settings['fit'] ?? 'contain' }};" alt="">
                    @elseif ($element->type === 'line')
                        <hr style="border-color: {{ $settings['border_color'] ?? '#d4d4d8' }};">
                    @elseif ($element->type === 'rectangle')
                        <div style="width:100%;height:100%;background:{{ $settings['background'] ?? 'transparent' }};border:{{ $settings['border_width'] ?? 1 }}px solid {{ $settings['border_color'] ?? '#d4d4d8' }};"></div>
                    @else
                        {{ $resolve($element->content ?? '') }}
                    @endif
                </div>
            @endforeach
        </div>
    @else
        {{-- No custom template assigned yet - a clean, functional default
            so a ticket is always printable even before an admin designs
            a branded template for its product. --}}
        <div class="ticket default-layout">
            <div id="qrcode"></div>
            <h1>{{ $product->name }} - {{ $variant->name }}</h1>
            <p class="ticket-number">{{ $ticket->ticket_number }}</p>
            <p class="meta">اعتبار: {{ $variables['valid_from'] }} تا {{ $variables['valid_until'] }}</p>
            <p class="meta">دفعات مجاز: {{ $ticket->allowed_uses }} - باقی‌مانده: {{ $ticket->remaining_uses }}</p>
            @if ($ticket->customer_name)
                <p class="meta">{{ $ticket->customer_name }}</p>
            @endif
        </div>
    @endif

    {{-- See resources/views/form-submissions/print.blade.php for the same
        vendored qrcodejs pattern this copies. --}}
    <script src="{{ asset('vendor/qrcodejs/qrcode.min.js') }}"></script>
    <script>
        if (window.QRCode && document.getElementById('qrcode')) {
            new QRCode(document.getElementById('qrcode'), {
                text: @json($ticket->qrToken->token),
                width: 96,
                height: 96,
            });
        }
    </script>
</body>
</html>
