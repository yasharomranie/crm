# سامانه مدیریت یکپارچه (CRM) — راهنمای جامع پروژه برای هوش مصنوعی و توسعه‌دهندگان

> **این فایل برای این نوشته شده که هر هوش مصنوعی (Claude، GPT، یا هر دستیار کدنویسی دیگری) با خوانده‌شدنِ همین یک فایل، کل پروژه، معماری، قراردادها، ماژول‌ها و روش کار را بفهمد و بتواند بدون پرس‌وجوی اضافه از همان‌جایی که کار متوقف شده ادامه بدهد.**
>
> اگر داری این پروژه را با یک AI جدید ادامه می‌دهی: اول همین فایل را کامل بده به مدل بخواند، بعد بگو دقیقاً چه می‌خواهی. اگر این فایل قدیمی شده (تاریخش با آخرین کامیت‌ها فاصله دارد)، از AI بخواه اول همین فایل را با وضعیت واقعی کد به‌روزرسانی کند و بعد کار جدید را شروع کند.

آخرین به‌روزرسانی این فایل: **۲۹ سپتامبر ۲۰۲۶** (بعد از مرج شدن PR #36 — فاز ۷ و ۸ مرکز سئو).

---

## ۱. این پروژه چیست؟

یک اپلیکیشن Laravel + Filament به نام داخلی **«سامانه مدیریت یکپارچه»** است — یک CRM/ERP همه‌کاره برای یک مجموعه‌ای که چند خط کسب‌وکار دارد: فروش بلیط و گیت ورودی (شبیه بلیط‌فروشی رویداد/موزه)، اقامتگاه/هتل، قرارداد و حقوقی، فرم‌ساز و گردش‌کار، مدیریت وظیفه/پروژه، اسناد، حضور و غیاب، چت داخلی، دستیار هوش مصنوعی، یک PWA مشتری، و یک ماژول بزرگ و مستقل به نام **«مرکز سئو»** که تمام کار سئوی سایت‌های مجموعه را خودکار می‌کند.

پنل ادمین تماماً با **Filament v5** ساخته شده، تمام UI راست‌به‌چپ (RTL) و فارسی است، و پروژه با کمک AI (Claude Code) به‌صورت فازبندی‌شده و هر فاز در یک PR جدا توسعه داده شده — یعنی این پروژه از ابتدا برای «ادامه‌دادن توسط AI» طراحی شده، نه فقط برایش این فایل نوشته شده باشد.

## ۲. پشته فناوری (Tech Stack)

| لایه | فناوری |
|---|---|
| بک‌اند | PHP **8.4** (هدف واقعی؛ `composer.json` هنوز `^8.3` نوشته)، Laravel **13** |
| پنل ادمین | Filament **v5.8** |
| دیتابیس | (طبق `config/database.php` بررسی شود — معمولاً MySQL/MariaDB) |
| صف/کیو | Laravel Queue — بدون workerِ دائمی روی هاست؛ با کرون `queue:work --stop-when-empty` تخلیه می‌شود (نگاه کن به `routes/console.php`) |
| مجوز/نقش | `spatie/laravel-permission` (نقش‌محور، اما **دینامیک** — بخش ۵ را ببین) |
| فایل/رسانه | `spatie/laravel-medialibrary` |
| لاگ فعالیت | `spatie/laravel-activitylog` |
| نوتیفیکیشن پوش | `laravel-notification-channels/webpush` |
| تاریخ شمسی | `morilog/jalali` + `mokhosh/filament-jalali` |
| فرانت‌اند | **Vite 8** + **Tailwind CSS v4** (پلاگین اول‌شخص Vite، بدون `tailwind.config.js`) |
| جاوااسکریپت | فقط Livewire/Alpine (پیش‌فرض Filament) — **هیچ** فریمورک React/Vue نیست |
| کتابخانه‌های JS خاص | `leaflet` (نقشه)، `qr-scanner` (اسکن QR سمت کلاینت)، `qrcodejs` (تولید QR بلیط)، `@laravel/multiplex` (برای Messenger) |
| تست | PHPUnit، فیچر تست‌ها اصل هستند (نه واحد) |
| فرمت‌دهی کد | Laravel Pint |

⚠️ **قانون مهم پروژه**: طبق `.ai/rules/general.md`، **هیچ‌وقت از CDN شخص‌ثالث لینک نده** (نه فونت گوگل، نه jsDelivr، نه unpkg). چون کاربران ایرانی‌اند و CDNها ممکن است فیلتر/کند باشند. همه فونت‌ها و کتابخانه‌های JS باید Self-hosted باشند (از طریق Vite برای فونت با دایرکتیو Blade به‌نام `@fonts`، و کپی به `public/vendor/<lib>/` برای کتابخانه‌های قدیمی سبک global-script).

## ۳. ساختار معماری و قراردادهای اصلی

```
app/
  Console/Commands/   دستورات Artisan زمان‌بندی‌شده (کرون)
  Domain/              سرویس‌های دامنه، گروه‌بندی‌شده بر اساس ماژول (مثل Domain/Seo, Domain/Ticketing, Domain/Webhooks)
  Enums/               Enumهای PHP 8.1+ (همیشه با کلید TitleCase، مثل FavoritePerson)
  Exceptions/          اکسپشن‌های دامنه‌محور (مثل SeoGoogleException با factory methodها)
  Filament/
    Resources/         هر ماژول CRUD یک Resource (لیست/فرم/جدول در ساب‌فولدرهای Pages/ و Tables/)
    Pages/              صفحات تنظیمات تکی، داشبوردها، ابزارهای غیر-CRUD (کشیر، اسکنر گیت، دستیار AI، ...)
  Http/Controllers/     کنترلرهای print/export/API/webhook
    Api/V1/External/    کنترلرهای API خارجی نسخه‌دار
  Jobs/                 جاب‌های صف‌دار (queued)
  Livewire/             کامپوننت‌های Livewire (بسیار کم؛ فقط جایی که Filament کافی نیست، مثل AiAssistantWidget)
  Models/                102+ مدل Eloquent
  Notifications/         کلاس‌های نوتیفیکیشن + Channels/ برای کانال سفارشی (SmsChannel)
  Observers/, Policies/, Providers/, Support/
```

### قراردادهای تکرارشونده که هر AI باید رعایت کند

1. **الگوی singleton برای تنظیمات**: هر مدلی که یک ردیف تنظیمات سراسری نگه می‌دارد (مثل `SeoRankTrackerSetting`, `SeoAlertSetting`, `SeoGoogleSetting`, `SmsSetting`) یک متد استاتیک `::current()` دارد که با `firstOrCreate(['id' => 1], [...defaults])` کار می‌کند. **باگ واقعی و تکرارشونده**: اگر defaultها به‌عنوان آرگومان دومِ `firstOrCreate` پاس داده نشوند، در همان اولین فراخوانیِ تاریخ عمرِ اپ، Eloquent شیء in-memory را بدون fetch مجدد برمی‌گرداند و فیلدهایی که ست نشده‌اند `null` می‌خوانند (با اینکه ستون دیتابیس مقدار پیش‌فرض درست را گرفته). همیشه defaultها را صریح پاس بده.
2. **خروجی «PDF»**: **هیچ کتابخانه PDF در این پروژه نیست و نباید اضافه شود.** قرارداد خانه این است: یک Blade view با CSS چاپ (`@media print`) بساز (شبیه `resources/views/reports/print.blade.php`, `tickets/print.blade.php`, `contracts/print.blade.php`, `form-submissions/print.blade.php`) + یک `*PrintController` که آن را با `auth` میدل‌ور نشان می‌دهد + دکمه `window.print()` در view. کاربر با Ctrl+P خودش PDF می‌گیرد. خروجی Excel واقعی هم هست (`openspout/openspout`، از طریق `TableExports.php`).
3. **بهترین‌کوشش برای APIهای خارجی اختیاری** (Google، پوش، SMS): همیشه try/catch کن، با `Log::warning` لاگ بزن، و اجازه بده عملیات اصلی با مقدار null در فیلد اختیاری موفق شود — نمونه: `CheckKeywordRankJob`, `PageSpeedInsightsClient::analyze()` در `TechnicalSeoAuditor`.
4. **RBAC دینامیک** (بخش ۵ را کامل بخوان قبل از افزودن هر ماژول جدید).
5. **Enum به‌عنوان کلید آرایه**: هرگز `Collection::countBy('columnCastToEnum')` استفاده نکن (پرتاب TypeError می‌کند چون instance اِنام کلید آرایه معتبر نیست) — همیشه `countBy(fn($m) => $m->column->value)`.
6. کدهای PHP: curly-brace همیشه، constructor property promotion، type-hint صریح روی همه پارامترها و return type، TitleCase برای کلید Enum، PHPDoc به‌جای کامنت خطی (فقط برای منطق واقعاً پیچیده کامنت خطی بگذار).
7. قبل از هر ادیت/پلن، فایل `.ai/rules/index.md` را بخوان (نگاشت glob→rule file) و هر فایل قانونیِ منطبق را بخوان؛ الان فقط یک قانون هست (بخش ۶).

## ۴. نقشهٔ کامل ماژول‌های کسب‌وکار

| ماژول | مدل‌های کلیدی | سرویس/دامنه | Filament Resources/Pages |
|---|---|---|---|
| **گیشه بلیط (Ticketing)** | Product, TicketVariant, TicketVariantPrice, Gate, GateRule, GateOfflineScanSync, AdmissionTicket, Order, OrderItem, Payment, Refund, DiscountCode, DiscountUsage, ComplimentaryIssue, QrToken, PrintLog, TicketTemplate(+Version+Element), TicketUsage(+Log) | `Domain/Ticketing/`: TicketIssuer, GateValidationService, DiscountService, RefundService | Products, TicketVariants, Gates, DiscountCodes + صفحات Cashier (فروش بلیط)، GateScanner (اسکن گیت، آفلاین‌سینک با IndexedDB) |
| **API فروش خارجی** | ExternalApiClient, IdempotencyKey | میدل‌ور `external-api.auth` + گیت `external-api.can:*` | ExternalApiClients resource؛ کنترلرهای `Api/V1/External/*` زیر `routes/api.php` (`v1/external`) |
| **اقامتی/هتلداری** | Room, RoomType, Booking, BookingCharge, SalesChannel, ChannelAllotment, PriceRule | — | Rooms, RoomTypes, Bookings, SalesChannels, ChannelAllotments, PriceRules + صفحات RoomRack (وضعیت زنده)، ChannelManagerDashboard |
| **CRM/فروش** | Company, Deal, Guest, GuestFeedback, GuestTicket, Tag, Department | — | Companies, Deals, Guests, GuestFeedback, GuestTickets, Tags, Departments + صفحه SalesPipeline (کانبان) |
| **حقوقی/قرارداد** | Contract, ContractCategory | — | Contracts, ContractCategories + صفحه ContractAssistant؛ ContractPrintController (چاپ + خروجی Word) |
| **وظایف/پروژه** | Task, TaskChecklistItem, TaskChecklistTemplate, TaskComment, TaskProof, ChecklistTemplateItem, Project, ProjectPhase, ProjectExpense | — | Tasks, TaskChecklistTemplates, Projects + کرون `GenerateRecurringChecklistTasks` |
| **فرم‌ساز/گردش‌کار** | WorkflowDefinition, WorkflowInstance, WorkflowStep, WorkflowAction, FormTemplate, FormField, FormSubmission, FormSubmissionValue | — | WorkflowDefinitions, FormTemplates, FormSubmissions + صفحه PeriodicFormReport؛ کرون `ArchiveApprovedFormSubmissions` |
| **اسناد** | Document, DocumentFolder, DocumentShare, DocumentVersion | — | Documents, DocumentFolders + صفحه DocumentArchiveBrowser (شبیه فایل‌منیجر)؛ DocumentDownloadController |
| **حضور و غیاب** | AttendanceRecord, WorkShift | — | AttendanceRecords, WorkShifts + صفحه MyAttendance؛ کرون `MarkAbsentees` |
| **ارتباطات داخلی/چت** | Message, Conversation, ConversationParticipant, Event, EventReminder | — | Events resource + صفحه Messenger؛ `SendEventReminders` کرون |
| **دستیار هوش مصنوعی** | AiConversation, AiMessage, AiProvider | — | AiProviders resource + صفحه AiAssistant (با Livewire `AiAssistantWidget`) |
| **وب‌هوک خروجی** | WebhookSubscription | `Domain/Webhooks/WebhookDispatcher` | Job: `DispatchWebhookJob` |
| **اپ مشتری / PWA** | WebAppBanner, WebAppSection, WebAppSectionItem | — | WebAppBanners, WebAppSections؛ `CustomerAppController` (مسیر عمومی `/app`)، `PushSubscriptionController` |
| **دسترسی/RBAC** | User, Role (spatie) | — | Users, Roles + کرون/دستور `access-control:sync` |
| **مرکز سئو** | ⬇️ بخش ۴.۱ جدا توضیح داده شده (بزرگ‌ترین ماژول) | | |

### ۴.۱. مرکز سئو (بزرگ‌ترین و آخرین ماژول توسعه‌یافته — ۸ فاز)

این ماژول تحلیل، هوش رقابتی، رصد رتبه، ممیزی فنی، هشدار و گزارش‌دهی خودکار سئو را برای چند سایت مجموعه انجام می‌دهد. **تاریخچهٔ فازبندی** (هر فاز = یک یا چند PR مرج‌شده):

1. **فاز ۱** — پایه: اتصال Google Search Console (OAuth)، انبار دادهٔ متریک‌های سئو، `SeoProject` resource.
2. **فاز ۲** — Intelligence Engine: `KeywordIntelligenceService`، `OpportunityEngine` (تشخیص فرصت سئو)، Keyword Profiles.
3. **فاز ۳** — هوش مصنوعی: `AiKeywordClassifier`, `AiKeywordDiscoveryService` (کشف/دسته‌بندی کلمهٔ کلیدی با AI).
4. **فاز ۴** — تقویم محتوا: `SeoContentItem`، اتصال فرصت/پیشنهاد → آیتم محتوا.
5. **فاز ۵** — سنجش عملکرد محتوای منتشرشده: `ContentPerformanceService`.
6. **فاز ۶** — یادگیری از نتیجه: `SeoOpportunityOutcome` + `OpportunityOutcomeService` (آیا فرصت واقعاً جواب داد؟).
7. **داشبورد مرکز سئو + ویجت آمار پروفایل کلمه کلیدی + ترکر رتبه** (`RankCheckService` + `GoogleCustomSearchClient`، کرون روزانه).
8. **فاز ۷** (PR #36، بخش اول) — سه افزونهٔ بزرگ روی هم:
   - **ممیزی فنی سئو**: کراولر خودنوشته (`TechnicalSeoAuditor`، بدون API پولی شخص‌ثالث)، تا ۴۰ صفحهٔ هم‌دامنه breadth-first، پرچم‌گذاریِ لینک خراب/عنوان و متادسکریپشن گم‌شده/تکراری/تصویر بدون alt/صفحه کند (>۳ثانیه)/robots.txt و sitemap.xml گمشده. هفتگی جمعه ۰۳:۰۰ به‌وقت تهران.
   - **هشدار خودکار سئو** (`SeoAlertService`): وقتی رتبهٔ یک کلمهٔ کلیدی افت کند یا فرصت High جدید کشف شود، از طریق SMS/پوش/ایمیل (کانال‌های موجود) به گیرندگان تنظیم‌شده اطلاع می‌دهد.
   - **گزارش دورهٔ مدیریتی** (`PeriodicReportService`): خلاصهٔ ماهانهٔ خودکار (اول هر ماه، ساعت ۰۴:۰۰ تهران) یا دستی برای هر بازهٔ دلخواه؛ خروجی همان الگوی خانهٔ «Blade + print».
9. **فاز ۸** (PR #36، بخش دوم، همان برنچ) — تکمیل ممیزی فنی:
   - **Core Web Vitals**: اتصال به Google PageSpeed Insights API v5 (`PageSpeedInsightsClient`) — از **همان کلید API** که برای رصد رتبه (Custom Search) ذخیره شده استفاده می‌کند؛ فقط لازم است ادمین «PageSpeed Insights API» را هم روی همان پروژهٔ Google Cloud فعال کند. امتیاز سرعت موبایل، LCP، CLS ثبت و در جدول/مودال Filament نشان داده می‌شود.
   - **داده ساخت‌یافته (Schema/JSON-LD)**: کراولر حالا صفحاتی که `<script type="application/ld+json">` ندارند را هم پرچم می‌کند.

**مسیرهای کلیدی برای شروع کار روی مرکز سئو**: `app/Domain/Seo/*`، مدل‌های `App\Models\Seo*`، `app/Filament/Resources/Seo*`، `app/Filament/Pages/Seo*`، `routes/console.php` (بخش کرون‌های سئو)، `tests/Feature/Seo/` (بزرگ‌ترین ساب‌فولدر تست، فعلاً ~۳۶ فایل).

## ۵. سیستم دسترسی (RBAC دینامیک)

**منبع حقیقت: `config/access-control.php`.** دو آرایهٔ اصلی:
- `resources`: هر ورودی یک ماژول CRUD مدل‌محور است (`['model' => X::class, 'label' => ..., 'group' => ...]`)؛ دستور `php artisan access-control:sync` به‌طور خودکار پرمیشن‌های list/view/create/edit/delete/bulk-delete را برای هرکدام می‌سازد و در UI نقش‌ها قابل تخصیص می‌شود. `DynamicPermissionPolicy` خودش را به هر مدلی که در این آرایه باشد bind می‌کند.
- `pages`: صفحات غیر-CRUD (مثل تنظیمات یا داشبوردها)؛ هرکدام فقط یک پرمیشن `.access` می‌گیرند، مگر اکشن‌های اضافه تعریف شده باشد (مثل `report`, `act`, `issue_complimentary`).

**نکتهٔ مهم**: در `AppServiceProvider`، `Gate::before` به کاربری که **هیچ نقشی** ندارد دسترسی کامل و بی‌محدودیت می‌دهد (این یک قرارداد سازگاری‌روبه‌عقبِ توثیق‌شده است، نه باگ). بنابراین برای تست کردنِ «دسترسی ممنوع»، باید کاربر یک نقش **خالی** (بدون هیچ پرمیشن) داشته باشد، نه بدون‌نقش — الگو: `Role::create(['name' => '...'])` + `$user->assignRole($role)`.

**برای افزودن یک ماژول جدید**: فقط یک ردیف در `config/access-control.php` اضافه کن، سپس `php artisan access-control:sync` بزن. کد دیگری لازم نیست.

## ۶. `.ai/rules/` — قوانین پروژه (باید قبل از هر ادیت خوانده شود)

- `.ai/rules/index.md`: نگاشت glob → فایل قانون. الان فقط یک ردیف: `**/*` → `general.md`.
- `.ai/rules/general.md`: **هرگز از CDN شخص‌ثالث لینک نده** (نه فونت گوگل، نه jsDelivr، نه unpkg) — چون کاربران در ایران‌اند و CDNها ممکن است کند/فیلتر باشند. فونت‌ها با Vite bundle می‌شوند (دایرکتیو Blade `@fonts` / `App\Filament\Support\LocalViteFontProvider` برای Filament)، کتابخانه‌های JS سبک قدیمی (Leaflet، qrcodejs) در `public/vendor/<lib>/` کپی می‌شوند. سرویس‌های دادهٔ زندهٔ شخص‌ثالث (مثل تایل نقشه) از این قانون مستثنی‌اند.

اگر فایل قانون جدیدی با `record-rule` (ابزار Laravel Boost) ثبت شد، اینجا هم به‌روزرسانی کن.

## ۷. Routes، Commands، Jobs

- **`routes/web.php`**: مسیرهای دانلود/چاپِ authenticated (اسناد، قرارداد+خروجی Word، فرم‌ها+خروجی Word، بلیط، گزارش دورهٔ سئو)، CRUD پوش‌ساب‌اسکریپشن، پیوست پیام/ویس، pull/push آفلاین‌سینک گیت، OAuth گوگل سئو، صفحهٔ راهنمای `/system-guide`، ریدایرکت `/api-docs`، و PWA عمومی مشتری در `/app`.
- **`routes/api.php`**: فقط API فروش خارجی نسخهٔ ۱ (`v1/external`, میدل‌ور `external-api.auth` + `external-api.can:<perm>`) — محصولات، تیکت‌ها، تخفیف، اسکن گیت، اتاق/رزرو، دیل CRM، فرم، اسناد، وب‌هوک. با کلید/رمز API، نه سشن کاربر.
- **`routes/console.php`**: زمان‌بندیِ کرون‌ها — چک‌لیست تکرارشونده روزانه، غایب‌گذاری روزانه، `queue:work --stop-when-empty` (بدون worker دائمی)، یادآوری رویداد هر ۵ دقیقه، ارزیابی نتیجهٔ فرصت سئو روزانه، رصد رتبه روزانه، ممیزی فنی هفتگی (جمعه ۰۳:۰۰)، گزارش دوره‌ای ماهانه (اول ماه ۰۴:۰۰). همه با تایم‌زون `Asia/Tehran`.
- **`app/Console/Commands/`**: `ArchiveApprovedFormSubmissions`, `CheckTrackedKeywordRanks`, `EvaluateSeoOpportunityOutcomes`, `GenerateRecurringChecklistTasks`, `GenerateSeoPeriodicReports`, `MarkAbsentees`, `RunTechnicalSeoAudits`, `SendEventReminders`, `SyncAccessControlPermissions`.
- **`app/Jobs/`**: `AnalyzeSeoProjectJob`, `CheckKeywordRankJob`, `ClassifySeoKeywordsJob`, `DiscoverSeoKeywordsJob`, `DispatchWebhookJob`, `RunTechnicalSeoAuditJob`, `SyncSearchConsoleDataJob`.

## ۸. تست‌ها

- `tests/Feature/` — بیشتر فلَت + ساب‌فولدرهای `Crm/`, `Documents/`, `Forms/`, `Hospitality/`, `Seo/` (۳۶+ فایل، بزرگ‌ترین)، `Ticketing/`, `Webhooks/`.
- در زمان نگارش این فایل: **~۹۹ فایل تست**، آخرین اجرای کامل تأییدشده **۵۴۰ تست موفق**.
- همیشه فیچر تست بنویس (`php artisan make:test --phpunit {Name}`، بدون `Feature/` در نام)؛ تست را روی factory مدل بساز، نه ساخت دستی.
- بعد از هر تغییر PHP: **`vendor/bin/pint --dirty --format agent`** را اجرا کن (نه `--test`).
- قبل از پوش: کوچک‌ترین مجموعهٔ تست مرتبط را اجرا کن، بعد کل سوییت پروژه را هم اگر تغییر گسترده بود.

## ۹. گردش‌کار Git / نحوهٔ همکاری با AI (خیلی مهم — همیشه رعایت شود)

- برنچ فعلی توسعه: **`claude/install-ui-ux-pro-max-tqw3sv`** (روی `main` مبتنی است).
- هر فاز/فیچر معمولاً یک PR جداست؛ اما اگر PR قبلی هنوز مرج نشده، فاز بعدی می‌تواند روی همان برنچ/PR باز اضافه شود (مثل فاز ۸ که به فاز ۷ در PR #36 اضافه شد).
- **هرگز بدون دستور صریح کاربر («مرج کن») مرج نکن.** حتی اگر همهٔ تست‌ها سبز باشند.
- خطای CI شناخته‌شده و preexisting: چک `PHP 8.3` روی هر PR fail می‌شود چون `composer.lock` نیازمند `php >=8.4.1` است (چون هدف واقعی اپ PHP 8.4 است) درحالی‌که ماتریس CI هنوز یک لِگ ۸.۳ دارد. این مربوط به این پروژه نیست و در PR‌های #33 تا #36 تکرار شده و در کامنت PR مستند شده — لازم نیست دوباره دربارهٔ همین ریشه کامنت بگذاری مگر چیزی واقعاً تغییر کرده باشد.
- Attribution کامیت/PR طبق دستورالعمل هر سشن (system-reminder) تعیین می‌شود، نه چیزی که در این فایل هارد-کد شود.
- بعد از مرج یک PR، از رویداد‌های GitHub آن (subscribe_pr_activity) خارج شو (unsubscribe).

## ۱۰. برای AI بعدی: از کجا شروع کن

1. اول `CLAUDE.md` (قوانین Laravel Boost) و `.ai/rules/index.md` + فایل‌های قانونِ منطبق را بخوان.
2. همین فایل (`README.md`) را برای نقشهٔ کلی و تاریخچه بخوان.
3. برای فهمیدن **دقیق وضعیت فعلی کد** (نه فقط این خلاصه)، این‌ها را چک کن:
   - `git log --oneline -30` برای دیدن آخرین فازها/PRها.
   - `config/access-control.php` برای لیست کامل و به‌روز ماژول‌ها.
   - `app/Models/` و `app/Filament/Resources/` برای لیست کامل و به‌روز مدل‌ها/ریسورس‌ها (این فایل ممکن است کمی قدیمی شود؛ همیشه با `ls` تأیید کن).
4. اگر کاربر گفت «ادامه بده» بدون جزئیات بیشتر، به تسک‌لیست فعال (اگر AI از ابزار Task استفاده می‌کند) یا به آخرین PR باز/آخرین کامیت‌ها نگاه کن تا بفهمی کار روی چه چیزی نیمه‌کاره مانده.
5. برای هر ماژول جدید: مدل + migration (با `make:model`)، سرویس دامنه در `app/Domain/<Module>/`، Filament Resource/Page، ثبت در `config/access-control.php` + `access-control:sync`، تست فیچر، `pint --dirty`، کامیت روی برنچ فیچر، و PR — و صبر برای «مرج کن».
6. اگر این فایل با کد واقعی مغایرت پیدا کرد (مثلاً ماژول جدیدی اضافه شده که اینجا نیست)، **همین فایل را به‌روز کن** تا AI بعدی گمراه نشود — این خودش بخشی از «اتمام کار» است، نه یک قدم اضافه.

---
*این فایل به‌صورت خودکار توسط Claude (دستیار کدنویسی) و به‌درخواست کاربر نوشته شده تا پیوستگی کار بین سشن‌های مختلف AI حفظ شود. اگر بخشی نادقیق یا قدیمی به نظر می‌رسد، همیشه کد واقعی مرجع نهایی است، نه این فایل.*
