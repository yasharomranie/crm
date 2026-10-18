@php
    // Reuses the exact same counts already shown as the sidebar's own
    // "پیام‌رسان" / "دستیار هوشمند" navigation badges (Messenger::
    // getNavigationBadge(), AiAssistant::getNavigationBadge()) - this just
    // mirrors their total onto the installed app's own icon via the
    // Badging API, for whichever of those is unsupported (only Chromium-
    // based browsers on desktop/Android implement it; iOS Safari and
    // Firefox silently no-op the optional-chained calls below).
    $user = auth()->user();
    $badgeCount = $user
        ? \App\Models\Message::totalUnreadFor($user) + count(\App\Filament\Pages\AiAssistant::visibleAlerts($user))
        : 0;
@endphp
<script>
    if (navigator.setAppBadge) {
        @if ($badgeCount > 0)
            navigator.setAppBadge({{ $badgeCount }}).catch(() => {});
        @else
            navigator.clearAppBadge().catch(() => {});
        @endif
    }
</script>
