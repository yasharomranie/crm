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
    $light = AiAssistantAvatar::mix($base, '#ffffff', 0.28);
    $dark = AiAssistantAvatar::mix($base, '#000000', 0.24);
    $deep = AiAssistantAvatar::mix($base, '#000000', 0.5);
    $bounce = AiAssistantAvatar::mix($base, '#ffffff', 0.45);
    $ink = AiAssistantAvatar::INK;

    // Each shape as markup with a %1$s paint slot: it is drawn once as the
    // body (gradient paint) and once in the highlight mask (white), so the
    // light never spills outside the silhouette.
    $shapeMarkup = [
        'rounded-square' => '<rect x="10" y="10" width="80" height="80" rx="28" fill="%1$s" />',
        'hexagon' => '<polygon points="87,50 68.5,82 31.5,82 13,50 31.5,18 68.5,18" fill="%1$s" stroke="%1$s" stroke-width="12" stroke-linejoin="round" />',
        'triangle' => '<polygon points="50,12 86,74 14,74" fill="%1$s" stroke="%1$s" stroke-width="20" stroke-linejoin="round" />',
        'teardrop' => '<path d="M54,12 C62,26 82,46 82,64 C82,82 68,92 50,92 C32,92 18,82 18,64 C18,48 38,34 54,12 Z" fill="%1$s" stroke="%1$s" stroke-width="6" stroke-linejoin="round" />',
        'cloud' => '<circle cx="28" cy="56" r="22" fill="%1$s" /><circle cx="50" cy="34" r="24" fill="%1$s" /><circle cx="73" cy="55" r="22" fill="%1$s" /><circle cx="35" cy="73" r="21" fill="%1$s" /><circle cx="66" cy="73" r="21" fill="%1$s" /><circle cx="50" cy="58" r="28" fill="%1$s" />',
        'flower' => '<circle cx="50" cy="28" r="24" fill="%1$s" /><circle cx="50" cy="72" r="24" fill="%1$s" /><circle cx="28" cy="50" r="24" fill="%1$s" /><circle cx="72" cy="50" r="24" fill="%1$s" /><circle cx="50" cy="50" r="22" fill="%1$s" />',
        'diamond' => '<polygon points="50,8 92,50 50,92 8,50" fill="%1$s" stroke="%1$s" stroke-width="14" stroke-linejoin="round" />',
        'pentagon' => '<polygon points="50,10 90,39 75,84 25,84 10,39" fill="%1$s" stroke="%1$s" stroke-width="14" stroke-linejoin="round" />',
        'circle' => '<circle cx="50" cy="50" r="42" fill="%1$s" />',
    ][$shape];

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
            <radialGradient id="{{ $uid }}" cx="34" cy="26" r="90" gradientUnits="userSpaceOnUse">
                <stop offset="0%" stop-color="{{ $light }}" />
                <stop offset="50%" stop-color="{{ $base }}" />
                <stop offset="100%" stop-color="{{ $dark }}" />
            </radialGradient>

            {{-- Puffy edges: a soft dark rim on the lower right, a soft light rim on the upper left. --}}
            <filter id="{{ $uid }}-puff" filterUnits="userSpaceOnUse" x="0" y="0" width="100" height="100" color-interpolation-filters="sRGB">
                <feComponentTransfer in="SourceAlpha" result="outside"><feFuncA type="table" tableValues="1 0" /></feComponentTransfer>
                <feGaussianBlur in="outside" stdDeviation="4.5" result="outsideBlur" />
                <feOffset in="outsideBlur" dx="-2.5" dy="-5" result="shadowShift" />
                <feComposite in="shadowShift" in2="SourceAlpha" operator="in" result="shadowInside" />
                <feFlood flood-color="{{ $deep }}" flood-opacity="0.55" />
                <feComposite in2="shadowInside" operator="in" result="rimShade" />
                <feOffset in="outsideBlur" dx="2" dy="3.5" result="lightShift" />
                <feComposite in="lightShift" in2="SourceAlpha" operator="in" result="lightInside" />
                <feFlood flood-color="#ffffff" flood-opacity="0.45" />
                <feComposite in2="lightInside" operator="in" result="rimLight" />
                <feMerge>
                    <feMergeNode in="SourceGraphic" />
                    <feMergeNode in="rimShade" />
                    <feMergeNode in="rimLight" />
                </feMerge>
            </filter>

            <filter id="{{ $uid }}-soft" filterUnits="userSpaceOnUse" x="-20" y="-20" width="140" height="140"><feGaussianBlur stdDeviation="5" /></filter>
            <filter id="{{ $uid }}-sharp" filterUnits="userSpaceOnUse" x="-20" y="-20" width="140" height="140"><feGaussianBlur stdDeviation="1.6" /></filter>

            <mask id="{{ $uid }}-mask" maskUnits="userSpaceOnUse" x="0" y="0" width="100" height="100">
                {!! sprintf($shapeMarkup, '#ffffff') !!}
            </mask>
        </defs>

        <g filter="url(#{{ $uid }}-puff)">
            {!! sprintf($shapeMarkup, 'url(#'.$uid.')') !!}
        </g>

        <g mask="url(#{{ $uid }}-mask)">
            <ellipse cx="55" cy="97" rx="36" ry="12" fill="{{ $bounce }}" opacity=".32" filter="url(#{{ $uid }}-soft)" />
            <ellipse cx="38" cy="27" rx="25" ry="14" fill="#ffffff" opacity=".36" transform="rotate(-28 38 27)" filter="url(#{{ $uid }}-soft)" />
            <ellipse cx="31" cy="24" rx="7.5" ry="3.8" fill="#ffffff" opacity=".6" transform="rotate(-30 31 24)" filter="url(#{{ $uid }}-sharp)" />
        </g>

        <g class="ai-avatar-eyes">
            <rect x="{{ 50 - 11 - 4.3 }}" y="{{ $eyeY - 10.5 }}" width="8.6" height="21" rx="4.3" fill="{{ $ink }}" transform="rotate({{ $tilt }} {{ 50 - 11 }} {{ $eyeY }})" />
            <rect x="{{ 50 + 11 - 4.3 }}" y="{{ $eyeY - 10.5 }}" width="8.6" height="21" rx="4.3" fill="{{ $ink }}" transform="rotate({{ -$tilt }} {{ 50 + 11 }} {{ $eyeY }})" />
        </g>
    </svg>
</span>
