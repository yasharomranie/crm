@props([
    'shape' => 'circle',
    'color' => 'Amber',
    'size' => 64,
    'animated' => true,
])

@php
    use App\Support\AiAssistantAvatar;
    use Illuminate\Support\Str;

    $shape = AiAssistantAvatar::isValidShape($shape) ? $shape : 'circle';
    $colorKey = AiAssistantAvatar::isValidColor($color) ? $color : 'Blue';
    $uid = 'ai-avatar-'.Str::random(8);

    // Soft "clay" body: one matte colour with a gentle light-to-shade
    // sweep (lit from the top left), no outline and no gloss. The sweep is
    // in user space so shapes made of several circles (cloud, clover) get
    // one continuous shading instead of one per circle.
    $base = AiAssistantAvatar::hex($colorKey);
    $light = AiAssistantAvatar::mix($base, '#ffffff', 0.2);
    $dark = AiAssistantAvatar::mix($base, '#000000', 0.16);
    $ink = AiAssistantAvatar::INK;

    // Two vertical pill eyes, no mouth. Where they sit and how they lean
    // depends on the shape (and a few colours lean inward, like the
    // reference characters).
    $eyeY = ['triangle' => 63, 'teardrop' => 62, 'cloud' => 56, 'rounded-square' => 49, 'pentagon' => 54, 'diamond' => 50][$shape] ?? 48;
    $tilt = match (true) {
        $colorKey === 'Orange' => 16,
        $shape === 'pentagon' => -14,
        default => 0,
    };
@endphp

@once
    <style>
        .ai-avatar-blob {
            filter: drop-shadow(0 5px 8px rgb(0 0 0 / .22));
            transition: transform .25s ease, filter .25s ease;
        }

        @media (prefers-reduced-motion: no-preference) {
            .ai-avatar-blob.is-animated {
                animation: ai-avatar-breathe 4.5s ease-in-out infinite;
            }

            .ai-avatar-blob.is-animated .ai-avatar-eyes {
                animation: ai-avatar-blink 5.5s ease-in-out infinite;
                transform-box: fill-box;
                transform-origin: center;
            }
        }

        @keyframes ai-avatar-breathe {
            0%, 100% { transform: scale(1) translateY(0); }
            50% { transform: scale(1.04) translateY(-3px); }
        }

        @keyframes ai-avatar-blink {
            0%, 92%, 100% { transform: scaleY(1); }
            96% { transform: scaleY(.1); }
        }
    </style>
@endonce

<span
    {{ $attributes->class(['ai-avatar-blob inline-flex shrink-0 items-center justify-center' => true, 'is-animated' => $animated]) }}
    style="width: {{ $size }}px; height: {{ $size }}px;"
>
    <svg viewBox="0 0 100 100" width="100%" height="100%" xmlns="http://www.w3.org/2000/svg">
        <defs>
            <radialGradient id="{{ $uid }}" cx="36" cy="28" r="84" gradientUnits="userSpaceOnUse">
                <stop offset="0%" stop-color="{{ $light }}" />
                <stop offset="55%" stop-color="{{ $base }}" />
                <stop offset="100%" stop-color="{{ $dark }}" />
            </radialGradient>
        </defs>

        @switch($shape)
            @case('rounded-square')
                <rect x="10" y="10" width="80" height="80" rx="28" fill="url(#{{ $uid }})" />
                @break

            @case('hexagon')
                <polygon points="87,50 68.5,82 31.5,82 13,50 31.5,18 68.5,18" fill="url(#{{ $uid }})" stroke="url(#{{ $uid }})" stroke-width="12" stroke-linejoin="round" />
                @break

            @case('triangle')
                <polygon points="50,12 86,74 14,74" fill="url(#{{ $uid }})" stroke="url(#{{ $uid }})" stroke-width="20" stroke-linejoin="round" />
                @break

            @case('teardrop')
                <path d="M54,12 C62,26 82,46 82,64 C82,82 68,92 50,92 C32,92 18,82 18,64 C18,48 38,34 54,12 Z" fill="url(#{{ $uid }})" stroke="url(#{{ $uid }})" stroke-width="6" stroke-linejoin="round" />
                @break

            @case('cloud')
                <circle cx="28" cy="56" r="22" fill="url(#{{ $uid }})" />
                <circle cx="50" cy="34" r="24" fill="url(#{{ $uid }})" />
                <circle cx="73" cy="55" r="22" fill="url(#{{ $uid }})" />
                <circle cx="35" cy="73" r="21" fill="url(#{{ $uid }})" />
                <circle cx="66" cy="73" r="21" fill="url(#{{ $uid }})" />
                <circle cx="50" cy="58" r="28" fill="url(#{{ $uid }})" />
                @break

            @case('flower')
                <circle cx="50" cy="28" r="24" fill="url(#{{ $uid }})" />
                <circle cx="50" cy="72" r="24" fill="url(#{{ $uid }})" />
                <circle cx="28" cy="50" r="24" fill="url(#{{ $uid }})" />
                <circle cx="72" cy="50" r="24" fill="url(#{{ $uid }})" />
                <circle cx="50" cy="50" r="22" fill="url(#{{ $uid }})" />
                @break

            @case('diamond')
                <polygon points="50,8 92,50 50,92 8,50" fill="url(#{{ $uid }})" stroke="url(#{{ $uid }})" stroke-width="14" stroke-linejoin="round" />
                @break

            @case('pentagon')
                <polygon points="50,10 90,39 75,84 25,84 10,39" fill="url(#{{ $uid }})" stroke="url(#{{ $uid }})" stroke-width="14" stroke-linejoin="round" />
                @break

            @default
                <circle cx="50" cy="50" r="42" fill="url(#{{ $uid }})" />
        @endswitch

        <g class="ai-avatar-eyes">
            <rect x="{{ 50 - 11 - 4.3 }}" y="{{ $eyeY - 10.5 }}" width="8.6" height="21" rx="4.3" fill="{{ $ink }}" transform="rotate({{ $tilt }} {{ 50 - 11 }} {{ $eyeY }})" />
            <rect x="{{ 50 + 11 - 4.3 }}" y="{{ $eyeY - 10.5 }}" width="8.6" height="21" rx="4.3" fill="{{ $ink }}" transform="rotate({{ -$tilt }} {{ 50 + 11 }} {{ $eyeY }})" />
        </g>
    </svg>
</span>
