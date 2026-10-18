<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $form->title }}</title>
<style>
  /* Self-hosted font (see .ai/rules/general.md) and self-contained CSS
     rather than the app's Vite bundle, so this embeddable page never
     depends on which classes the last `npm run build` happened to include. */
  @font-face {
    font-family: 'Vazirmatn';
    font-style: normal;
    font-weight: 100 900;
    font-display: swap;
    src: url('/system-guide-assets/fonts/vazirmatn.woff2') format('woff2');
  }

  * { box-sizing: border-box; }

  :root {
    --bg: #f6f4f0;
    --card: #ffffff;
    --ink: #1c1917;
    --ink-soft: #57534e;
    --border: #e4ded4;
    --accent: #d97706;
    --accent-dark: #b45309;
    --danger: #b91c1c;
    --success-bg: #ecfdf5;
    --success-ink: #065f46;
  }

  html, body { margin: 0; padding: 0; }

  body {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24px 16px;
    background: var(--bg);
    color: var(--ink);
    font-family: 'Vazirmatn', Tahoma, sans-serif;
    line-height: 1.8;
  }

  .card {
    width: 100%;
    max-width: 440px;
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 28px 24px;
  }

  h1 { margin: 0 0 6px; font-size: 1.35rem; font-weight: 700; }
  .lead { margin: 0 0 22px; color: var(--ink-soft); font-size: 0.95rem; }

  label { display: block; margin-bottom: 4px; font-size: 0.9rem; font-weight: 600; }
  .field { margin-bottom: 16px; }

  input[type="text"], input[type="tel"], textarea {
    width: 100%;
    padding: 11px 12px;
    border: 1px solid var(--border);
    border-radius: 10px;
    background: #fff;
    color: var(--ink);
    font: inherit;
  }

  input:focus, textarea:focus {
    outline: 2px solid var(--accent);
    outline-offset: 1px;
    border-color: var(--accent);
  }

  textarea { min-height: 96px; resize: vertical; }

  .error { margin: 4px 0 0; color: var(--danger); font-size: 0.85rem; }

  button {
    width: 100%;
    padding: 12px;
    border: 0;
    border-radius: 10px;
    background: var(--accent);
    color: #fff;
    font: inherit;
    font-weight: 700;
    cursor: pointer;
  }

  button:hover { background: var(--accent-dark); }
  button:focus-visible { outline: 3px solid var(--accent-dark); outline-offset: 2px; }

  .success {
    padding: 18px;
    border-radius: 12px;
    background: var(--success-bg);
    color: var(--success-ink);
    font-weight: 600;
    text-align: center;
  }

  /* Honeypot - off-screen rather than display:none, which some bots skip. */
  .hp { position: absolute; right: -9999px; height: 0; overflow: hidden; }
</style>
</head>
<body>
  <main class="card">
    @if (session('lead_submitted'))
      <div class="success" role="status">{{ $form->success_message }}</div>
    @else
      <h1>{{ $form->title }}</h1>
      @if ($form->description)
        <p class="lead">{{ $form->description }}</p>
      @endif

      <form method="POST" action="{{ route('lead-capture.store', $form->slug) }}" novalidate>
        @csrf

        <div class="field">
          <label for="name">نام و نام خانوادگی</label>
          <input type="text" id="name" name="name" value="{{ old('name') }}" autocomplete="name" required>
          @error('name') <p class="error">{{ $message }}</p> @enderror
        </div>

        <div class="field">
          <label for="mobile">شماره‌ی موبایل</label>
          <input type="tel" id="mobile" name="mobile" value="{{ old('mobile') }}" inputmode="numeric" autocomplete="tel" dir="ltr" placeholder="09123456789" required>
          @error('mobile') <p class="error">{{ $message }}</p> @enderror
        </div>

        <div class="field">
          <label for="message">توضیحات (اختیاری)</label>
          <textarea id="message" name="message">{{ old('message') }}</textarea>
          @error('message') <p class="error">{{ $message }}</p> @enderror
        </div>

        <div class="field">
          <label for="sms_consent" style="display:flex;gap:.5rem;align-items:flex-start;font-weight:400">
            <input type="checkbox" id="sms_consent" name="sms_consent" value="1" {{ old('sms_consent') ? 'checked' : '' }} style="width:auto;margin-top:.25rem">
            <span>با دریافت پیامک‌های اطلاع‌رسانی و پیشنهادهای ویژه موافقم (هر زمان می‌توانم لغو کنم).</span>
          </label>
        </div>

        <div class="hp" aria-hidden="true">
          <label for="website">وب‌سایت</label>
          <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
        </div>

        <button type="submit">ثبت درخواست</button>
      </form>
    @endif
  </main>
</body>
</html>
