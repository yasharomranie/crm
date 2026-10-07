{{--
    The persistent bottom tab bar of the گنجنامه customer app - shared by
    every customer-app-*.blade.php page (home, bookings, wallet, tickets,
    support, profile), fed by App\Models\CustomerAppNavItem::forDisplay()
    so an admin can relabel/re-icon/reorder/hide a tab from "منوی پایین
    اپ مشتریان" in the panel without a deploy. Expects a $navItems
    collection of ['key','label','icon','url','active'] arrays, plus an
    $authNavItem array (['url','label','initial','active']) for the
    raised, round ورود/پروفایل button - see
    AppServiceProvider::registerCustomerAppNavComposer(). That button's
    own destination is fixed in code (never admin-editable), same as
    every other tab's route.
--}}
<style>
    .bottom-nav {
        position: fixed;
        inset-inline: 0;
        bottom: 0;
        z-index: 20;
        max-width: 30rem;
        margin: 0 auto;
        background: #fff;
        border-top: 1px solid var(--line);
        padding: .45rem .5rem calc(.45rem + env(safe-area-inset-bottom));
        display: flex;
        box-shadow: 0 -2px 12px rgb(0 0 0 / .04);
    }

    .bottom-nav-auth {
        position: absolute;
        top: -1.55rem;
        left: 50%;
        transform: translateX(-50%);
        text-decoration: none;
    }

    .bottom-nav-auth .circle {
        box-sizing: border-box;
        width: 3.6rem;
        height: 3.6rem;
        aspect-ratio: 1 / 1;
        border-radius: 50%;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: .05rem;
        background: linear-gradient(135deg, var(--orange), var(--orange-dark));
        border: 3px solid #fff;
        box-shadow: 0 8px 18px rgb(194 65 12 / .38), inset 0 1px 1px rgb(255 255 255 / .35);
        transition: transform .15s ease, box-shadow .15s ease;
    }

    .bottom-nav-auth:active .circle {
        transform: scale(.93);
        box-shadow: 0 4px 10px rgb(194 65 12 / .35), inset 0 1px 1px rgb(255 255 255 / .25);
    }

    .bottom-nav-auth .circle svg {
        width: 18px;
        height: 18px;
        color: #fff;
    }

    .bottom-nav-auth .circle .initial {
        font-size: .95rem;
        font-weight: 800;
        line-height: 1;
        color: #fff;
    }

    .bottom-nav-auth .circle .label {
        font-size: .56rem;
        font-weight: 700;
        line-height: 1;
        color: #fff;
    }

    .bottom-nav-auth[data-active="true"] .circle {
        background: linear-gradient(135deg, var(--orange-dark), #9a3412);
    }

    .bottom-nav a {
        flex: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: .25rem;
        text-decoration: none;
        color: var(--ink-faint);
        padding: .3rem 0;
        border-radius: .7rem;
    }

    .bottom-nav a svg {
        width: 22px;
        height: 22px;
    }

    .bottom-nav a span {
        font-size: .68rem;
        font-weight: 600;
    }

    .bottom-nav a[data-active="true"] {
        color: var(--orange-dark);
    }

    .bottom-nav a[data-active="true"] svg {
        color: var(--orange);
    }

    /* Keeps page content from sitting under the fixed bar - each page's
       own scrollable container should carry this class. */
    .has-bottom-nav {
        padding-bottom: calc(4.6rem + env(safe-area-inset-bottom));
    }
</style>

<nav class="bottom-nav">
    @foreach ($navItems as $item)
        <a href="{{ $item['url'] }}" data-active="{{ $item['active'] ? 'true' : 'false' }}">
            @switch($item['icon'])
                @case('home')
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11.5 12 4l9 7.5"/><path d="M5 10v9a1 1 0 0 0 1 1h4v-6h4v6h4a1 1 0 0 0 1-1v-9"/></svg>
                    @break
                @case('bag')
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8h12l1 12.5a1 1 0 0 1-1 1.5H6a1 1 0 0 1-1-1.5L6 8Z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/></svg>
                    @break
                @case('wallet')
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="6" width="18" height="13" rx="2"/><path d="M3 10h18"/><path d="M16 14.5h2"/></svg>
                    @break
                @case('star')
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 3 2.6 5.9 6.4.6-4.8 4.3 1.4 6.3L12 17l-5.6 3.1 1.4-6.3-4.8-4.3 6.4-.6L12 3Z"/></svg>
                    @break
                @case('ticket')
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 8a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v2a2 2 0 0 0 0 4v2a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-2a2 2 0 0 0 0-4V8Z"/></svg>
                    @break
                @case('heart')
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20s-7-4.4-9.5-8.7C.8 8 2 4.5 5.5 4a4.8 4.8 0 0 1 6.5 2 4.8 4.8 0 0 1 6.5-2c3.5.5 4.7 4 3 7.3C19 15.6 12 20 12 20Z"/></svg>
                    @break
                @case('bell')
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8a6 6 0 0 1 12 0c0 5 2 6 2 6H4s2-1 2-6Z"/><path d="M10 20a2 2 0 0 0 4 0"/></svg>
                    @break
                @default
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="3.5"/><path d="M4.5 20a7.5 7.5 0 0 1 15 0"/></svg>
            @endswitch
            <span>{{ $item['label'] }}</span>
        </a>
    @endforeach

    <a href="{{ $authNavItem['url'] }}" class="bottom-nav-auth" data-active="{{ $authNavItem['active'] ? 'true' : 'false' }}">
        <span class="circle">
            @if ($authNavItem['initial'])
                <span class="initial">{{ $authNavItem['initial'] }}</span>
            @else
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="3.5"/><path d="M4.5 20a7.5 7.5 0 0 1 15 0"/></svg>
            @endif
            <span class="label">{{ $authNavItem['label'] }}</span>
        </span>
    </a>
</nav>
