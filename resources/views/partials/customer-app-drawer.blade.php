{{--
    The app-style side drawer opened by the hamburger button in the
    گنجنامه customer-app home page's topbar (see customer-app.blade.php's
    .menu-trigger) - a quick-access account panel (avatar, wallet balance,
    profile/bookings/wallet/support links, logout), slid in from the right
    since the page is RTL and the trigger sits in that corner. Expects a
    $guest variable in scope (already shared by every customer-app-*
    controller).
--}}
<style>
    .app-menu-backdrop {
        position: fixed;
        inset: 0;
        z-index: 29;
        background: rgb(28 25 23 / .45);
        opacity: 0;
        pointer-events: none;
        transition: opacity .2s ease;
    }

    .app-menu-backdrop.is-open {
        opacity: 1;
        pointer-events: auto;
    }

    .app-menu-drawer {
        position: fixed;
        top: 0;
        bottom: 0;
        right: 0;
        z-index: 30;
        width: min(82vw, 20rem);
        background: #fff;
        box-shadow: -10px 0 30px rgb(0 0 0 / .12);
        padding: max(1.2rem, env(safe-area-inset-top)) 1.1rem 1.2rem;
        display: flex;
        flex-direction: column;
        gap: 1.1rem;
        transform: translateX(100%);
        transition: transform .25s ease;
        overflow-y: auto;
    }

    .app-menu-drawer.is-open {
        transform: translateX(0);
    }

    .app-menu-head {
        display: flex;
        align-items: center;
        gap: .7rem;
    }

    .app-menu-avatar {
        flex-shrink: 0;
        width: 2.8rem;
        height: 2.8rem;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--orange), var(--orange-dark));
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
        font-weight: 800;
    }

    .app-menu-head .name {
        font-size: .92rem;
        font-weight: 700;
        display: block;
    }

    .app-menu-head .muted {
        font-size: .76rem;
        color: var(--ink-faint);
        display: block;
        margin-top: .1rem;
    }

    .app-menu-close {
        margin-inline-start: auto;
        appearance: none;
        border: none;
        background: var(--paper);
        width: 1.9rem;
        height: 1.9rem;
        border-radius: 50%;
        color: var(--ink-faint);
        font-size: 1rem;
        line-height: 1;
        cursor: pointer;
    }

    .app-menu-wallet {
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: var(--orange-soft);
        color: var(--orange-dark);
        border-radius: .85rem;
        padding: .75rem .9rem;
        font-size: .82rem;
    }

    .app-menu-wallet strong {
        font-size: .92rem;
    }

    .app-menu-links {
        display: flex;
        flex-direction: column;
        gap: .3rem;
    }

    .app-menu-links a {
        padding: .7rem .5rem;
        border-radius: .7rem;
        text-decoration: none;
        color: var(--ink);
        font-size: .86rem;
        font-weight: 600;
    }

    .app-menu-logout {
        margin-top: auto;
    }

    .app-menu-logout button {
        appearance: none;
        width: 100%;
        border: 1px solid var(--line);
        background: none;
        border-radius: .8rem;
        padding: .75rem 1rem;
        font-family: inherit;
        font-size: .85rem;
        font-weight: 700;
        color: #b91c1c;
        cursor: pointer;
    }
</style>

<div class="app-menu-backdrop" data-app-menu-backdrop></div>

<aside class="app-menu-drawer" data-app-menu-drawer>
    <div class="app-menu-head">
        <span class="app-menu-avatar">
            @if ($guest)
                {{ mb_substr($guest->name, 0, 1) }}
            @else
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="3.5"/><path d="M4.5 20a7.5 7.5 0 0 1 15 0"/></svg>
            @endif
        </span>
        <div>
            @if ($guest)
                <span class="name">{{ $guest->name }}</span>
                @if ($guest->mobile)
                    <span class="muted">{{ $guest->mobile }}</span>
                @endif
            @else
                <span class="name">مهمان گنجنامه</span>
            @endif
        </div>
        <button type="button" class="app-menu-close" data-app-menu-close aria-label="بستن منو">×</button>
    </div>

    @if ($guest)
        <div class="app-menu-wallet">
            <span>موجودی کیف پول</span>
            <strong>{{ number_format($guest->wallet_balance) }} تومان</strong>
        </div>
    @endif

    <nav class="app-menu-links">
        @if ($guest)
            <a href="{{ route('customer-app.profile') }}">مشاهده پروفایل</a>
            <a href="{{ route('customer-app.bookings') }}">سفارش‌های من</a>
            <a href="{{ route('customer-app.wallet') }}">کیف پول و باشگاه</a>
            <a href="{{ route('customer-app.tickets') }}">بلیط و چک‌این</a>
            <a href="{{ route('customer-app.support') }}">تیکت پشتیبانی</a>
        @else
            <a href="{{ route('customer-app.auth.login') }}">ورود / ثبت‌نام</a>
        @endif
    </nav>

    @if ($guest)
        <form method="POST" action="{{ route('customer-app.auth.logout') }}" class="app-menu-logout">
            @csrf
            <button type="submit">خروج از حساب کاربری</button>
        </form>
    @endif
</aside>

<script>
    (function () {
        var openBtn = document.querySelector('[data-app-menu-open]');
        var closeBtn = document.querySelector('[data-app-menu-close]');
        var backdrop = document.querySelector('[data-app-menu-backdrop]');
        var drawer = document.querySelector('[data-app-menu-drawer]');

        if (!openBtn || !drawer || !backdrop) {
            return;
        }

        function openMenu() {
            backdrop.classList.add('is-open');
            drawer.classList.add('is-open');
        }

        function closeMenu() {
            backdrop.classList.remove('is-open');
            drawer.classList.remove('is-open');
        }

        openBtn.addEventListener('click', openMenu);
        closeBtn.addEventListener('click', closeMenu);
        backdrop.addEventListener('click', closeMenu);
    })();
</script>
