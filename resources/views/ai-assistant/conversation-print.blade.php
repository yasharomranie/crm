<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $conversation->title ?: 'گفتگو با دستیار هوشمند' }}</title>
    <style>
        body { font-family: Vazirmatn, Tahoma, sans-serif; color: #1f2937; margin: 0; padding: 24px 16px; line-height: 1.9; }
        main { max-width: 760px; margin: 0 auto; }
        h1 { font-size: 1.25rem; margin: 0 0 4px; }
        .meta { color: #6b7280; font-size: .8rem; margin-bottom: 24px; }
        .turn { margin-bottom: 16px; padding: 10px 14px; border-radius: 10px; break-inside: avoid; }
        .turn.user { background: #fef3c7; }
        .turn.assistant { background: #f3f4f6; }
        .who { font-size: .72rem; font-weight: 700; color: #6b7280; margin-bottom: 2px; }
        table { width: 100%; border-collapse: collapse; margin: 8px 0; font-size: .85rem; }
        th, td { border: 1px solid #d1d5db; padding: 4px 8px; text-align: right; }
        thead { background: #e5e7eb; }
        a { color: #1d4ed8; }
        @media print { body { padding: 0; } .turn { border: 1px solid #e5e7eb; background: none !important; } }
    </style>
</head>
<body @if ($auto) onload="window.print()" @endif>
    <main>
        <h1>{{ $conversation->title ?: 'گفتگو با دستیار هوشمند' }}</h1>
        <p class="meta">{{ $conversation->created_at->toJalali('Y/m/d H:i') }}</p>

        @foreach ($messages as $message)
            @php $isUser = $message->role === \App\Enums\AiMessageRole::User; @endphp
            <section class="turn {{ $isUser ? 'user' : 'assistant' }}">
                <div class="who">{{ $isUser ? 'شما' : 'دستیار هوشمند' }} · {{ $message->created_at->toJalali('H:i') }}</div>
                @if ($isUser)
                    <div style="white-space: pre-line">{{ $message->content }}</div>
                @else
                    {!! \App\Support\SafeMarkdown::toHtml($message->content) !!}
                @endif
            </section>
        @endforeach
    </main>
</body>
</html>
