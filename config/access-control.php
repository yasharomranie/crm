<?php

use App\Filament\Pages\AiAssistant;
use App\Filament\Pages\AiAssistantsHub;
use App\Filament\Pages\AiAssistantUsageDashboard;
use App\Filament\Pages\Cashier;
use App\Filament\Pages\ChannelManagerDashboard;
use App\Filament\Pages\CommunicationInbox;
use App\Filament\Pages\ContractAssistant;
use App\Filament\Pages\CustomerAppAuthSettings;
use App\Filament\Pages\CustomerAppGoogleSettings;
use App\Filament\Pages\CustomerAppNavSettings;
use App\Filament\Pages\CustomerValueReportPage;
use App\Filament\Pages\DocumentArchiveBrowser;
use App\Filament\Pages\DuplicateGuests;
use App\Filament\Pages\GateScanner;
use App\Filament\Pages\Messenger;
use App\Filament\Pages\MyAttendance;
use App\Filament\Pages\PaymentGatewaySettings;
use App\Filament\Pages\PeriodicFormReport;
use App\Filament\Pages\ProductionDeploy;
use App\Filament\Pages\RoomRack;
use App\Filament\Pages\SalesPipeline;
use App\Filament\Pages\SeoActionQueue;
use App\Filament\Pages\SeoAlertSettings;
use App\Filament\Pages\SeoContentGaps;
use App\Filament\Pages\SeoDashboard;
use App\Filament\Pages\SeoGoogleSettings;
use App\Filament\Pages\SeoPillarClusterMap;
use App\Filament\Pages\SeoRankTrackerSettings;
use App\Filament\Pages\SeoSitemapAnalyzer;
use App\Filament\Pages\SeoTrafficBreakdown;
use App\Filament\Pages\SeoWpFleet;
use App\Filament\Pages\SmsSettings;
use App\Filament\Pages\TicketingReportsPage;
use App\Filament\Pages\TripPlannerReportPage;
use App\Filament\Pages\TripPlannerSettings;
use App\Filament\Pages\WalletLiabilityReport;
use App\Filament\Pages\WalletSettings;
use App\Filament\Pages\WebAppDomainSettings;
use App\Filament\Pages\WhatsAppSettings;
use App\Models\AiProvider;
use App\Models\AiScheduledReport;
use App\Models\AttendanceRecord;
use App\Models\Attraction;
use App\Models\Booking;
use App\Models\ChannelAllotment;
use App\Models\Company;
use App\Models\Contract;
use App\Models\ContractCategory;
use App\Models\Deal;
use App\Models\DealAutomationRule;
use App\Models\Department;
use App\Models\DiscountCode;
use App\Models\Document;
use App\Models\DocumentFolder;
use App\Models\Event;
use App\Models\ExternalApiClient;
use App\Models\FormSubmission;
use App\Models\FormTemplate;
use App\Models\Gate;
use App\Models\Guest;
use App\Models\GuestFeedback;
use App\Models\GuestSegment;
use App\Models\GuestTicket;
use App\Models\LeadCaptureForm;
use App\Models\Order;
use App\Models\PriceRule;
use App\Models\Product;
use App\Models\Project;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\SalesChannel;
use App\Models\SeoContentItem;
use App\Models\SeoKeywordProfile;
use App\Models\SeoKeywordSuggestion;
use App\Models\SeoOpportunity;
use App\Models\SeoPeriodicReport;
use App\Models\SeoProject;
use App\Models\SeoTechnicalAudit;
use App\Models\SeoTrackedKeyword;
use App\Models\SeoWpPost;
use App\Models\Tag;
use App\Models\Task;
use App\Models\TaskChecklistTemplate;
use App\Models\TicketVariant;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkShift;
use Spatie\Permission\Models\Role;

return [

    /*
    |--------------------------------------------------------------------------
    | نقشهٔ ماژول‌های مبتنی بر منبع (Filament Resource)
    |--------------------------------------------------------------------------
    |
    | این‌ها منبع اصلی سیستم کنترل سطح دسترسی داینامیک هستند. برای هر ماژول،
    | مجوزهای استاندارد CRUD (مشاهدهٔ فهرست، مشاهدهٔ جزئیات، ایجاد، ویرایش،
    | حذف، حذف گروهی) به‌صورت خودکار توسط دستور «access-control:sync» ساخته
    | می‌شوند و از طریق صفحهٔ «نقش‌ها و سطوح دسترسی» به هر نقش قابل تخصیص‌اند.
    | افزودن ماژول جدید یعنی افزودن یک ردیف به این آرایه؛ نیازی به تغییر جای
    | دیگری از سیستم نیست.
    |
    */
    'resources' => [
        'companies' => ['model' => Company::class, 'label' => 'شرکت‌های هلدینگ', 'group' => 'زیرساخت سازمانی'],
        'departments' => ['model' => Department::class, 'label' => 'دپارتمان‌ها', 'group' => 'زیرساخت سازمانی'],
        'users' => ['model' => User::class, 'label' => 'کاربران', 'group' => 'زیرساخت سازمانی'],
        'tags' => ['model' => Tag::class, 'label' => 'برچسب‌ها (تگ‌ها)', 'group' => 'زیرساخت سازمانی'],
        'roles' => ['model' => Role::class, 'label' => 'نقش‌ها و سطوح دسترسی', 'group' => 'زیرساخت سازمانی'],

        'work-shifts' => ['model' => WorkShift::class, 'label' => 'شیفت‌های کاری', 'group' => 'حضور و غیاب'],
        'attendance-records' => ['model' => AttendanceRecord::class, 'label' => 'رکوردهای حضور', 'group' => 'حضور و غیاب'],

        'documents' => ['model' => Document::class, 'label' => 'اسناد', 'group' => 'مدیریت اسناد'],
        'document-folders' => ['model' => DocumentFolder::class, 'label' => 'پوشه‌های اسناد', 'group' => 'مدیریت اسناد'],

        'form-templates' => ['model' => FormTemplate::class, 'label' => 'قالب‌های فرم', 'group' => 'فرم‌ساز و گردش‌کار'],
        'form-submissions' => ['model' => FormSubmission::class, 'label' => 'فرم‌ها و کارتابل تأییدات', 'group' => 'فرم‌ساز و گردش‌کار'],
        'workflow-definitions' => ['model' => WorkflowDefinition::class, 'label' => 'گردش‌کارهای تأیید', 'group' => 'فرم‌ساز و گردش‌کار'],

        'tasks' => ['model' => Task::class, 'label' => 'تسک‌ها', 'group' => 'وظایف و پروژه‌ها'],
        'projects' => ['model' => Project::class, 'label' => 'پروژه‌ها', 'group' => 'وظایف و پروژه‌ها'],
        'task-checklist-templates' => ['model' => TaskChecklistTemplate::class, 'label' => 'قالب‌های چک‌لیست تکرارشونده', 'group' => 'وظایف و پروژه‌ها'],

        'rooms' => ['model' => Room::class, 'label' => 'اتاق‌ها و ویلاها', 'group' => 'اقامتی و هتلداری'],
        'room-types' => ['model' => RoomType::class, 'label' => 'انواع اتاق/ویلا', 'group' => 'اقامتی و هتلداری'],
        'bookings' => ['model' => Booking::class, 'label' => 'رزروها', 'group' => 'اقامتی و هتلداری'],
        'sales-channels' => ['model' => SalesChannel::class, 'label' => 'کانال‌های فروش', 'group' => 'اقامتی و هتلداری'],
        'channel-allotments' => ['model' => ChannelAllotment::class, 'label' => 'سهمیه‌بندی کانال‌ها', 'group' => 'اقامتی و هتلداری'],
        'price-rules' => ['model' => PriceRule::class, 'label' => 'قیمت‌گذاری پویا', 'group' => 'اقامتی و هتلداری'],

        'guests' => ['model' => Guest::class, 'label' => 'مهمانان', 'group' => 'CRM و مشتریان'],
        'deals' => ['model' => Deal::class, 'label' => 'معاملات فروش', 'group' => 'CRM و مشتریان'],
        'guest-feedback' => ['model' => GuestFeedback::class, 'label' => 'بازخوردها و NPS', 'group' => 'CRM و مشتریان'],
        'guest-tickets' => ['model' => GuestTicket::class, 'label' => 'تیکت‌های پشتیبانی', 'group' => 'CRM و مشتریان'],
        'deal-automation-rules' => ['model' => DealAutomationRule::class, 'label' => 'اتوماسیون پایپ‌لاین', 'group' => 'CRM و مشتریان'],
        'guest-segments' => ['model' => GuestSegment::class, 'label' => 'بخش‌بندی مشتریان', 'group' => 'CRM و مشتریان'],
        'lead-capture-forms' => ['model' => LeadCaptureForm::class, 'label' => 'فرم‌های جذب سرنخ', 'group' => 'CRM و مشتریان'],

        'ai-providers' => ['model' => AiProvider::class, 'label' => 'ارائه‌دهندگان هوش مصنوعی', 'group' => 'دستیار هوشمند مدیریتی'],
        'ai-scheduled-reports' => ['model' => AiScheduledReport::class, 'label' => 'گزارش‌های زمان‌بندی‌شدهٔ دستیار', 'group' => 'دستیار هوشمند مدیریتی'],

        'contracts' => ['model' => Contract::class, 'label' => 'آرشیو قراردادها', 'group' => 'حقوقی و قرارداد'],
        'contract-categories' => ['model' => ContractCategory::class, 'label' => 'دسته‌های قرارداد', 'group' => 'حقوقی و قرارداد'],

        'events' => ['model' => Event::class, 'label' => 'رویدادها', 'group' => 'ارتباطات داخلی'],

        'ticketing-products' => ['model' => Product::class, 'label' => 'محصولات (جاذبه‌ها)', 'group' => 'گیشه بلیط'],
        'ticketing-variants' => ['model' => TicketVariant::class, 'label' => 'انواع بلیت', 'group' => 'گیشه بلیط'],
        'orders' => ['model' => Order::class, 'label' => 'سفارش‌ها و بلیت‌ها', 'group' => 'گیشه بلیط'],
        'ticketing-gates' => ['model' => Gate::class, 'label' => 'گیت‌ها', 'group' => 'گیشه بلیط'],
        'ticketing-discount-codes' => ['model' => DiscountCode::class, 'label' => 'کدهای تخفیف', 'group' => 'گیشه بلیط'],

        'external-api-clients' => ['model' => ExternalApiClient::class, 'label' => 'اکانت‌های API خارجی', 'group' => 'زیرساخت سازمانی'],

        'seo-projects' => ['model' => SeoProject::class, 'label' => 'پروژه‌های سئو', 'group' => 'مرکز سئو'],
        'seo-opportunities' => ['model' => SeoOpportunity::class, 'label' => 'فرصت‌های سئو', 'group' => 'مرکز سئو'],
        'seo-wp-posts' => ['model' => SeoWpPost::class, 'label' => 'محتوای سایت (وردپرس)', 'group' => 'مرکز سئو'],
        'seo-keyword-profiles' => ['model' => SeoKeywordProfile::class, 'label' => 'کلمات کلیدی', 'group' => 'مرکز سئو'],
        'seo-keyword-suggestions' => ['model' => SeoKeywordSuggestion::class, 'label' => 'پیشنهادهای کلمات کلیدی', 'group' => 'مرکز سئو'],
        'seo-content-items' => ['model' => SeoContentItem::class, 'label' => 'تقویم محتوا', 'group' => 'مرکز سئو'],
        'seo-tracked-keywords' => ['model' => SeoTrackedKeyword::class, 'label' => 'ترکر کلمه کلیدی', 'group' => 'مرکز سئو'],
        'seo-technical-audits' => ['model' => SeoTechnicalAudit::class, 'label' => 'ممیزی فنی سئو', 'group' => 'مرکز سئو'],
        'seo-periodic-reports' => ['model' => SeoPeriodicReport::class, 'label' => 'گزارش دوره‌ای مدیریتی', 'group' => 'مرکز سئو'],

        'attractions' => ['model' => Attraction::class, 'label' => 'جاذبه‌های گردشگری', 'group' => 'تریپ‌پلنر'],
    ],

    /*
    |--------------------------------------------------------------------------
    | نقشهٔ ماژول‌های مبتنی بر صفحه (Filament Page)
    |--------------------------------------------------------------------------
    |
    | این‌ها منبع CRUD نیستند (مثل دستیار هوشمند یا پیام‌رسان)، پس فقط یک
    | مجوز «دسترسی» برای هرکدام ساخته می‌شود.
    |
    */
    'pages' => [
        'ai-assistants-hub' => ['page' => AiAssistantsHub::class, 'label' => 'مرکز دستیاران هوشمند', 'group' => 'دستیار هوشمند مدیریتی'],
        'ai-assistant-usage-dashboard' => ['page' => AiAssistantUsageDashboard::class, 'label' => 'آمار مصرف دستیاران', 'group' => 'دستیار هوشمند مدیریتی'],
        'whatsapp-settings' => ['page' => WhatsAppSettings::class, 'label' => 'اتصال واتس‌اپ', 'group' => 'دستیار هوشمند مدیریتی'],
        'ai-assistant' => [
            'page' => AiAssistant::class,
            'label' => 'دستیار هوشمند',
            'group' => 'دستیار هوشمند مدیریتی',
            'actions' => [
                'access' => 'دسترسی به صفحه',
                'report' => 'گزارش‌گیری (ابزارهای خواندنی)',
                'act' => 'اقدام (ایجاد تسک و موارد مشابه)',
            ],
        ],
        'messenger' => ['page' => Messenger::class, 'label' => 'پیام‌رسان', 'group' => 'ارتباطات داخلی'],
        'my-attendance' => ['page' => MyAttendance::class, 'label' => 'حضور و غیاب من', 'group' => 'حضور و غیاب'],
        'channel-manager-dashboard' => ['page' => ChannelManagerDashboard::class, 'label' => 'داشبورد کانال‌های فروش', 'group' => 'اقامتی و هتلداری'],
        'room-rack' => ['page' => RoomRack::class, 'label' => 'رک اتاق (وضعیت زنده)', 'group' => 'اقامتی و هتلداری'],
        'customer-value-report' => ['page' => CustomerValueReportPage::class, 'label' => 'ارزش و ریزش مشتری', 'group' => 'CRM و مشتریان'],
        'communication-inbox' => ['page' => CommunicationInbox::class, 'label' => 'صندوق پیام', 'group' => 'CRM و مشتریان'],
        'duplicate-guests' => ['page' => DuplicateGuests::class, 'label' => 'مشتریان تکراری', 'group' => 'CRM و مشتریان'],
        'sales-pipeline' => ['page' => SalesPipeline::class, 'label' => 'پایپ‌لاین فروش (کانبان)', 'group' => 'CRM و مشتریان'],
        'contract-assistant' => ['page' => ContractAssistant::class, 'label' => 'دستیار حقوقی و قرارداد', 'group' => 'حقوقی و قرارداد'],
        'periodic-form-report' => ['page' => PeriodicFormReport::class, 'label' => 'گزارش ادواری فرم‌ها', 'group' => 'فرم‌ساز و گردش‌کار'],
        'document-browser' => ['page' => DocumentArchiveBrowser::class, 'label' => 'بایگانی اسناد (فایل‌منیجر)', 'group' => 'مدیریت اسناد'],
        'wallet-liability-report' => ['page' => WalletLiabilityReport::class, 'label' => 'گزارش بدهی کیف پول', 'group' => 'CRM و مشتریان'],
        'wallet-settings' => ['page' => WalletSettings::class, 'label' => 'تنظیمات کیف پول و کش‌بک', 'group' => 'CRM و مشتریان'],
        'payment-gateway-settings' => ['page' => PaymentGatewaySettings::class, 'label' => 'درگاه پرداخت آنلاین', 'group' => 'زیرساخت سازمانی'],
        'sms-settings' => ['page' => SmsSettings::class, 'label' => 'تنظیمات پیامک', 'group' => 'زیرساخت سازمانی'],
        'production-deploy' => ['page' => ProductionDeploy::class, 'label' => 'دیپلوی سرور', 'group' => 'زیرساخت سازمانی'],
        'seo-google-settings' => ['page' => SeoGoogleSettings::class, 'label' => 'تنظیمات اتصال گوگل', 'group' => 'مرکز سئو'],
        'seo-dashboard' => ['page' => SeoDashboard::class, 'label' => 'داشبورد سئو', 'group' => 'مرکز سئو'],
        'seo-rank-tracker-settings' => ['page' => SeoRankTrackerSettings::class, 'label' => 'تنظیمات ترکر کلمه کلیدی', 'group' => 'مرکز سئو'],
        'seo-pillar-cluster-map' => ['page' => SeoPillarClusterMap::class, 'label' => 'نقشه موضوعی (پیلار-کلاستر)', 'group' => 'مرکز سئو'],
        'seo-action-queue' => ['page' => SeoActionQueue::class, 'label' => 'صف اقدام', 'group' => 'مرکز سئو'],
        'seo-sitemap-analyzer' => ['page' => SeoSitemapAnalyzer::class, 'label' => 'تحلیل‌گر Sitemap', 'group' => 'مرکز سئو'],
        'seo-wp-fleet' => ['page' => SeoWpFleet::class, 'label' => 'وضعیت سایت‌های وردپرسی', 'group' => 'مرکز سئو'],
        'seo-content-gaps' => ['page' => SeoContentGaps::class, 'label' => 'خلأ پوشش محتوا', 'group' => 'مرکز سئو'],
        'seo-traffic-breakdown' => ['page' => SeoTrafficBreakdown::class, 'label' => 'ترکیب ترافیک', 'group' => 'مرکز سئو'],
        'seo-alert-settings' => ['page' => SeoAlertSettings::class, 'label' => 'تنظیمات هشدار سئو', 'group' => 'مرکز سئو'],
        'cashier' => [
            'page' => Cashier::class,
            'label' => 'فروش بلیط (صندوق)',
            'group' => 'گیشه بلیط',
            'actions' => [
                'access' => 'دسترسی به صفحه',
                'issue_complimentary' => 'صدور بلیت رایگان/اهدایی',
            ],
        ],
        'ticketing-reports' => ['page' => TicketingReportsPage::class, 'label' => 'گزارش‌های گیشه', 'group' => 'گیشه بلیط'],
        'gate-scanner' => ['page' => GateScanner::class, 'label' => 'اسکن بلیط (گیت)', 'group' => 'گیشه بلیط'],
        'web-app-domain-settings' => ['page' => WebAppDomainSettings::class, 'label' => 'دامنه اختصاصی', 'group' => 'وب‌اپ مشتریان'],
        'customer-app-google-settings' => ['page' => CustomerAppGoogleSettings::class, 'label' => 'ورود با گوگل', 'group' => 'وب‌اپ مشتریان'],
        'customer-app-auth-settings' => ['page' => CustomerAppAuthSettings::class, 'label' => 'روش‌های ورود', 'group' => 'وب‌اپ مشتریان'],
        'customer-app-nav-settings' => ['page' => CustomerAppNavSettings::class, 'label' => 'منوی پایین اپ', 'group' => 'وب‌اپ مشتریان'],
        'trip-planner-report' => ['page' => TripPlannerReportPage::class, 'label' => 'گزارش تریپ‌پلنر', 'group' => 'تریپ‌پلنر'],
        'trip-planner-settings' => ['page' => TripPlannerSettings::class, 'label' => 'تنظیمات دستیار سفر', 'group' => 'تریپ‌پلنر'],
    ],
];
