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
    $colorKey = AiAssistantAvatar::isValidColor($color) ? $color : 'Amber';
    $uid = 'ai-avatar-'.Str::random(8);

    // Same glossy, gradient-shaded "mascot" look as public/images/mascot-
    // avatar.svg (the existing floating-widget character) - a light-to-
    // dark sweep of the SAME color token, never a flat silhouette, so a
    // blob reads as a soft 3D character rather than an icon.
    $light = AiAssistantAvatar::shade($colorKey, 300);
    $base = AiAssistantAvatar::shade($colorKey, 500);
    $dark = AiAssistantAvatar::shade($colorKey, 600);
    $ink = AiAssistantAvatar::shade($colorKey, 950);
@endphp

@once
    <style>
        .ai-avatar-blob {
            filter: drop-shadow(0 6px 14px rgb(0 0 0 / .18));
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
            <linearGradient id="{{ $uid }}" x1="20" y1="8" x2="80" y2="95" gradientUnits="userSpaceOnUse">
                <stop offset="0%" stop-color="{{ $light }}" />
                <stop offset="55%" stop-color="{{ $base }}" />
                <stop offset="100%" stop-color="{{ $dark }}" />
            </linearGradient>
        </defs>

        @switch($shape)
            @case('rounded-square')
                <rect x="10" y="10" width="80" height="80" rx="24" fill="url(#{{ $uid }})" />
                @break

            @case('hexagon')
                <polygon points="94,50 72,88.1 28,88.1 6,50 28,11.9 72,11.9" fill="url(#{{ $uid }})" stroke="url(#{{ $uid }})" stroke-width="12" stroke-linejoin="round" />
                @break

            @case('triangle')
                <polygon points="50,6 88.1,72 11.9,72" fill="url(#{{ $uid }})" stroke="url(#{{ $uid }})" stroke-width="14" stroke-linejoin="round" />
                @break

            @case('teardrop')
                <path d="M50,6 C58,22 82,46 82,64 C82,82 68,94 50,94 C32,94 18,82 18,64 C18,46 42,22 50,6 Z" fill="url(#{{ $uid }})" />
                @break

            @case('cloud')
                <circle cx="35" cy="60" r="22" fill="url(#{{ $uid }})" />
                <circle cx="60" cy="55" r="26" fill="url(#{{ $uid }})" />
                <circle cx="78" cy="65" r="18" fill="url(#{{ $uid }})" />
                <circle cx="50" cy="74" r="24" fill="url(#{{ $uid }})" />
                @break

            @case('flower')
                <circle cx="50" cy="28" r="24" fill="url(#{{ $uid }})" />
                <circle cx="50" cy="72" r="24" fill="url(#{{ $uid }})" />
                <circle cx="28" cy="50" r="24" fill="url(#{{ $uid }})" />
                <circle cx="72" cy="50" r="24" fill="url(#{{ $uid }})" />
                <circle cx="50" cy="50" r="18" fill="url(#{{ $uid }})" />
                @break

            @case('diamond')
                <polygon points="50,6 94,50 50,94 6,50" fill="url(#{{ $uid }})" stroke="url(#{{ $uid }})" stroke-width="12" stroke-linejoin="round" />
                @break

            @case('pentagon')
                <polygon points="50,6 91.86,36.4 75.86,85.6 24.14,85.6 8.14,36.4" fill="url(#{{ $uid }})" stroke="url(#{{ $uid }})" stroke-width="12" stroke-linejoin="round" />
                @break

            @default
                <circle cx="50" cy="50" r="42" fill="url(#{{ $uid }})" />
        @endswitch

        {{-- A simple face (eyes + a soft smile) - what the user actually
            asked for: without it these read as flat geometric icons, not
            "characters". Coordinates are centered on this viewBox's own
            visual middle (~50,60), which comfortably sits inside every
            shape above, matching public/images/mascot-avatar.svg's own
            eye/mouth placement. --}}
        <ellipse cx="22" cy="63" rx="5.5" ry="3.5" fill="#ffffff" opacity=".22" />
        <ellipse cx="78" cy="63" rx="5.5" ry="3.5" fill="#ffffff" opacity=".22" />

        <g class="ai-avatar-eyes">
            <circle cx="39" cy="58" r="4.2" fill="{{ $ink }}" />
            <circle cx="61" cy="58" r="4.2" fill="{{ $ink }}" />
            <circle cx="40.6" cy="56.4" r="1.3" fill="#ffffff" />
            <circle cx="62.6" cy="56.4" r="1.3" fill="#ffffff" />
        </g>

        <path d="M41,70 Q50,76.5 59,70" stroke="{{ $ink }}" stroke-width="2.6" fill="none" stroke-linecap="round" opacity=".55" />
    </svg>
</span>
