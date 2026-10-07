<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

    <title>ورود — گنجنامه</title>
    <meta name="description" content="ورود یا ثبت‌نام در اپ مشتریان گنجنامه.">

    @include('partials.customer-app-pwa-head')

    @fonts

    @vite(['resources/css/app.css'])

    <style>
        :root {
            color-scheme: light;
            --orange: #f97316;
            --orange-dark: #c2410c;
            --orange-soft: #ffedd5;
            --green: #15803d;
            --green-soft: #dcfce7;
            --red: #b91c1c;
            --red-soft: #fee2e2;
            --ink: #1c1917;
            --ink-soft: #57534e;
            --ink-faint: #a8a29e;
            --paper: #fff7ed;
            --line: #f1e4d3;
        }

        * {
            box-sizing: border-box;
        }

        html, body {
            height: 100%;
        }

        body {
            margin: 0;
            min-height: 100dvh;
            font-family: 'Vazirmatn', ui-sans-serif, system-ui, sans-serif;
            background: var(--paper);
            color: var(--ink);
        }

        .shell {
            max-width: 26rem;
            margin: 0 auto;
            min-height: 100dvh;
            background: #fff;
            box-shadow: 0 0 40px rgb(0 0 0 / .05);
            padding: max(2rem, env(safe-area-inset-top)) 1.25rem 3rem;
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .brand {
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: .4rem;
        }

        .brand .mark {
            width: 56px;
            height: 56px;
            border-radius: 18px;
            background: linear-gradient(135deg, var(--orange), var(--green));
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 1.4rem;
            font-weight: 800;
        }

        .brand h1 {
            font-size: 1.05rem;
            margin: 0;
        }

        .brand p {
            font-size: .8rem;
            color: var(--ink-faint);
            margin: 0;
        }

        .errors {
            background: var(--red-soft);
            color: var(--red);
            border-radius: .75rem;
            padding: .7rem .9rem;
            font-size: .82rem;
            display: flex;
            flex-direction: column;
            gap: .25rem;
        }

        .mode-switch {
            display: flex;
            background: var(--paper);
            border: 1px solid var(--line);
            border-radius: .85rem;
            padding: .25rem;
            gap: .25rem;
        }

        .mode-switch button {
            flex: 1;
            appearance: none;
            border: none;
            background: none;
            font-family: inherit;
            font-size: .85rem;
            color: var(--ink-faint);
            padding: .55rem 0;
            border-radius: .6rem;
            cursor: pointer;
        }

        .mode-switch button[aria-selected="true"] {
            background: #fff;
            color: var(--orange-dark);
            font-weight: 700;
            box-shadow: 0 1px 3px rgb(0 0 0 / .08);
        }

        .panel {
            display: none;
            flex-direction: column;
            gap: .9rem;
        }

        .panel[data-open="true"] {
            display: flex;
        }

        label.field {
            display: flex;
            flex-direction: column;
            gap: .35rem;
            font-size: .8rem;
            color: var(--ink-soft);
        }

        label.field input {
            font-family: inherit;
            font-size: .92rem;
            border: 1px solid var(--line);
            border-radius: .7rem;
            padding: .7rem .85rem;
            background: var(--paper);
            color: var(--ink);
            outline: none;
        }

        label.field input:focus {
            border-color: var(--orange);
        }

        .btn {
            appearance: none;
            border: none;
            font-family: inherit;
            font-size: .92rem;
            font-weight: 700;
            border-radius: .8rem;
            padding: .8rem 1rem;
            cursor: pointer;
            text-align: center;
            text-decoration: none;
            display: block;
        }

        .btn-primary {
            background: var(--orange);
            color: #fff;
        }

        .btn-ghost {
            background: var(--paper);
            color: var(--ink);
            border: 1px solid var(--line);
        }

        .hint {
            font-size: .78rem;
            color: var(--ink-faint);
            text-align: center;
        }

        .hint button, .hint a {
            color: var(--orange-dark);
            font-weight: 700;
            background: none;
            border: none;
            font-family: inherit;
            font-size: inherit;
            cursor: pointer;
            padding: 0;
            text-decoration: none;
        }

        .divider {
            display: flex;
            align-items: center;
            gap: .7rem;
            color: var(--ink-faint);
            font-size: .75rem;
        }

        .divider::before, .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--line);
        }

        .google-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: .6rem;
        }

        .back {
            text-align: center;
            font-size: .8rem;
        }

        .back a {
            color: var(--ink-faint);
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="shell">
        <div class="brand">
            <span class="mark">گ</span>
            <h1>به گنجنامه خوش آمدید</h1>
            <p>برای مشاهده‌ی رزروها، کیف پول و باشگاه مشتریان وارد شوید.</p>
        </div>

        @if ($errors->any())
            <div class="errors">
                @foreach ($errors->all() as $error)
                    <span>{{ $error }}</span>
                @endforeach
            </div>
        @endif

        <div class="mode-switch" role="tablist">
            <button type="button" data-mode-tab="otp" aria-selected="true">ورود با کد پیامکی</button>
            <button type="button" data-mode-tab="password" aria-selected="false">رمز عبور</button>
        </div>

        {{-- ---------- OTP mode ---------- --}}
        <div class="panel" data-mode-panel="otp" data-open="true">
            @if ($otpRequestedFor)
                <form method="POST" action="{{ route('customer-app.auth.otp.verify') }}" style="display:flex;flex-direction:column;gap:.9rem">
                    @csrf
                    <input type="hidden" name="mobile" value="{{ $otpRequestedFor }}">
                    <label class="field">
                        کد ارسال‌شده به {{ $otpRequestedFor }}
                        <input type="text" name="code" inputmode="numeric" maxlength="6" autocomplete="one-time-code" autofocus required>
                    </label>
                    <button type="submit" class="btn btn-primary">تایید و ورود</button>
                    <p class="hint"><a href="{{ route('customer-app.auth.login') }}">شماره را عوض کن</a></p>
                </form>
            @else
                <form method="POST" action="{{ route('customer-app.auth.otp.request') }}">
                    @csrf
                    <label class="field">
                        شماره موبایل
                        <input type="tel" name="mobile" placeholder="09xxxxxxxxx" autocomplete="tel" required>
                    </label>
                    <button type="submit" class="btn btn-primary" style="margin-top:.9rem">ارسال کد ورود</button>
                </form>
            @endif
        </div>

        {{-- ---------- Password mode ---------- --}}
        <div class="panel" data-mode-panel="password">
            <form method="POST" action="{{ route('customer-app.auth.login.attempt') }}" data-password-form="login">
                @csrf
                <label class="field">
                    شماره موبایل یا ایمیل
                    <input type="text" name="login" autocomplete="username" required>
                </label>
                <label class="field">
                    رمز عبور
                    <input type="password" name="password" autocomplete="current-password" required>
                </label>
                <button type="submit" class="btn btn-primary" style="margin-top:.4rem">ورود</button>
                <p class="hint">حساب ندارید؟ <button type="button" data-password-switch="register">ثبت‌نام کنید</button></p>
            </form>

            <form method="POST" action="{{ route('customer-app.auth.register') }}" data-password-form="register" style="display:none">
                @csrf
                <label class="field">
                    نام و نام خانوادگی
                    <input type="text" name="name" autocomplete="name" required>
                </label>
                <label class="field">
                    شماره موبایل
                    <input type="tel" name="mobile" placeholder="09xxxxxxxxx" autocomplete="tel" required>
                </label>
                <label class="field">
                    رمز عبور
                    <input type="password" name="password" autocomplete="new-password" required>
                </label>
                <label class="field">
                    تکرار رمز عبور
                    <input type="password" name="password_confirmation" autocomplete="new-password" required>
                </label>
                <button type="submit" class="btn btn-primary" style="margin-top:.4rem">ثبت‌نام</button>
                <p class="hint">قبلاً ثبت‌نام کرده‌اید؟ <button type="button" data-password-switch="login">وارد شوید</button></p>
            </form>
        </div>

        @if ($googleConfigured)
            <div class="divider">یا</div>
            <a href="{{ route('customer-app.auth.google.redirect') }}" class="btn btn-ghost google-btn">
                <svg width="18" height="18" viewBox="0 0 24 24"><path fill="#4285F4" d="M23.52 12.27c0-.85-.07-1.47-.22-2.12H12v3.84h6.6c-.13 1.1-.86 2.76-2.47 3.88l-.02.15 3.59 2.78.25.02c2.28-2.1 3.57-5.19 3.57-8.55"/><path fill="#34A853" d="M12 24c3.24 0 5.95-1.07 7.93-2.9l-3.78-2.93c-1.02.71-2.4 1.21-4.15 1.21-3.17 0-5.86-2.1-6.82-5l-.14.01-3.73 2.89-.05.13C3.25 21.3 7.28 24 12 24"/><path fill="#FBBC05" d="M5.18 14.38A7.4 7.4 0 0 1 4.77 12c0-.83.15-1.64.4-2.38l-.01-.16-3.78-2.94-.12.06A12 12 0 0 0 0 12c0 1.93.46 3.76 1.26 5.38z"/><path fill="#EA4335" d="M12 4.75c2.25 0 3.77.97 4.64 1.79l3.39-3.31C17.94 1.19 15.24 0 12 0 7.28 0 3.25 2.7 1.26 6.62l3.91 3.04c.97-2.9 3.66-5 6.83-5"/></svg>
                ورود با حساب گوگل
            </a>
        @endif

        <p class="back"><a href="{{ route('customer-app') }}">بازگشت به خانه</a></p>
    </div>

    <script>
        (function () {
            var modeTabs = document.querySelectorAll('[data-mode-tab]');
            modeTabs.forEach(function (tab) {
                tab.addEventListener('click', function () {
                    modeTabs.forEach(function (t) { t.setAttribute('aria-selected', 'false'); });
                    tab.setAttribute('aria-selected', 'true');
                    document.querySelectorAll('[data-mode-panel]').forEach(function (panel) {
                        panel.setAttribute('data-open', panel.dataset.modePanel === tab.dataset.modeTab ? 'true' : 'false');
                    });
                });
            });

            var switches = document.querySelectorAll('[data-password-switch]');
            switches.forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var target = btn.dataset.passwordSwitch;
                    document.querySelectorAll('[data-password-form]').forEach(function (form) {
                        form.style.display = form.dataset.passwordForm === target ? 'flex' : 'none';
                        form.style.flexDirection = 'column';
                        form.style.gap = '.9rem';
                    });
                });
            });
        })();
    </script>
</body>
</html>
