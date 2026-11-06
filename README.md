# سامانه مدیریت یکپارچه (CRM/ERP گنجنامه)

یک سامانهٔ فارسی (راست‌به‌چپ، تقویم شمسی) روی **Laravel 13 + Filament 5.8 + Livewire** برای یک مجموعهٔ گردشگری و اقامتی: اقامت و هتلداری، گیشهٔ بلیط، CRM، قرارداد، فرم‌ساز و گردش‌کار، وظیفه و پروژه، پروژه‌های دیجیتال، حضور و غیاب، اسناد، دستیار هوشمند، مرکز سئو، وب‌اپ مشتریان (PWA در `/app`) و «راهنمای هوشمند گردشگری» (شناسایی اشیا با دوربین گوشی).

> **منبع اصلی و به‌روز اطلاعات پروژه، پوشهٔ [`project-continuity/`](project-continuity/README.md) است.** این فایل عمداً کوتاه است تا قدیمی نشود.

## از کجا شروع کنم؟
| فایل | کاربرد |
|---|---|
| [`project-continuity/README.md`](project-continuity/README.md) | ورودی و ترتیب خواندن |
| [`project-continuity/INSTRUCTIONS.md`](project-continuity/INSTRUCTIONS.md) | قوانین کار روی پروژه (قراردادهای کد، دسترسی‌ها، گردش‌کار Git/PR، ایمنی) |
| [`project-continuity/CONTEXT.md`](project-continuity/CONTEXT.md) | معماری، ماژول‌ها، داده، پیکربندی، وضعیت فعلی |
| `project-continuity/HANDOFF-<تاریخ>.md` | آخرین تحویل کار: چه شده، چه مانده، قدم بعدی (آخرین تاریخ را بخوانید) |
| [`project-continuity/DEPLOYMENT.md`](project-continuity/DEPLOYMENT.md) | دیپلوی و نگهداری روی هاست بدون ترمینال |
| [`project-continuity/DECISIONS.md`](project-continuity/DECISIONS.md) · [`TEST-LOG.md`](project-continuity/TEST-LOG.md) · [`OPEN-QUESTIONS.md`](project-continuity/OPEN-QUESTIONS.md) | تصمیم‌ها، سابقهٔ تست، سؤال‌های باز |
| `CLAUDE.md` / `AGENTS.md` | رهنمودهای Laravel Boost |
| `.ai/rules/` | قوانین پروژه (از جمله: بدون CDN، به‌روز نگه‌داشتن مستندات) |
| `/system-guide` در خود سامانه | **مرکز آموزش** کاربران (فایل `resources/views/system-guide.blade.php`) |

## قانون ثابت پایان هر امکان تازه
با هر امکان، اصلاح یا تصمیم تازه، در **همان PR** این دو را به‌روز کنید:
1. **`project-continuity/`** (زمینه، تحویل‌کار، تصمیم‌ها، لاگ تست، سؤال‌های باز، دیپلوی در صورت لزوم)
2. **مرکز آموزش** (`/system-guide`): فصل یا بخش مربوط به همان امکان

(قانون کامل: `.ai/rules/project-continuity.md`.)

## فناوری در یک نگاه
PHP 8.4 (CI روی 8.4 و 8.5) · Laravel 13 · Filament 5.8 · Vite 8 + Tailwind 4 · Spatie (permission، activitylog، medialibrary) · تست با PHPUnit 12 · صف و نشست و کش روی دیتابیس · فونت و کتابخانه‌ها همه روی خود سرور (بدون CDN).

## نکات حیاتی
- هاست **ترمینال ندارد**؛ دیپلوی با دکمهٔ «دیپلوی سرور» در پنل است. خطاها را از «گزارش خطاهای سرور» (مدیر) ببینید.
- تنها راستی‌آزمای تست، **CI روی PHP 8.4 و 8.5** است؛ محیط سندباکس (PHP 8.3) نمی‌تواند برنامه را اجرا کند.
- هر PR فقط وقتی مرج می‌شود که مالک بگوید «مرج کن» و CI هر دو نسخه سبز باشد.
- کاربر **بدون نقش** دسترسی کامل دارد؛ برای کارکنان حتماً نقش تعیین کنید.
- هیچ مقدار محرمانه‌ای (`.env`، کلیدها) در مخزن یا مستندات نوشته نشود.
