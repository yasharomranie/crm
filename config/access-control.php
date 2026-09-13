<?php

use App\Filament\Pages\AiAssistant;
use App\Filament\Pages\ChannelManagerDashboard;
use App\Filament\Pages\Messenger;
use App\Filament\Pages\MyAttendance;
use App\Filament\Pages\RoomRack;
use App\Filament\Pages\SalesPipeline;
use App\Models\AiProvider;
use App\Models\AttendanceRecord;
use App\Models\Booking;
use App\Models\ChannelAllotment;
use App\Models\Deal;
use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentFolder;
use App\Models\FormTemplate;
use App\Models\Guest;
use App\Models\GuestFeedback;
use App\Models\GuestTicket;
use App\Models\PriceRule;
use App\Models\Project;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\SalesChannel;
use App\Models\Tag;
use App\Models\Task;
use App\Models\TaskChecklistTemplate;
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
        'departments' => ['model' => Department::class, 'label' => 'دپارتمان‌ها', 'group' => 'زیرساخت سازمانی'],
        'users' => ['model' => User::class, 'label' => 'کاربران', 'group' => 'زیرساخت سازمانی'],
        'tags' => ['model' => Tag::class, 'label' => 'برچسب‌ها (تگ‌ها)', 'group' => 'زیرساخت سازمانی'],
        'roles' => ['model' => Role::class, 'label' => 'نقش‌ها و سطوح دسترسی', 'group' => 'زیرساخت سازمانی'],

        'work-shifts' => ['model' => WorkShift::class, 'label' => 'شیفت‌های کاری', 'group' => 'حضور و غیاب'],
        'attendance-records' => ['model' => AttendanceRecord::class, 'label' => 'رکوردهای حضور', 'group' => 'حضور و غیاب'],

        'documents' => ['model' => Document::class, 'label' => 'اسناد', 'group' => 'مدیریت اسناد'],
        'document-folders' => ['model' => DocumentFolder::class, 'label' => 'پوشه‌های اسناد', 'group' => 'مدیریت اسناد'],

        'form-templates' => ['model' => FormTemplate::class, 'label' => 'قالب‌های فرم', 'group' => 'فرم‌ساز و گردش‌کار'],
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

        'ai-providers' => ['model' => AiProvider::class, 'label' => 'ارائه‌دهندگان هوش مصنوعی', 'group' => 'دستیار هوشمند مدیریتی'],
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
        'ai-assistant' => ['page' => AiAssistant::class, 'label' => 'دستیار هوشمند', 'group' => 'دستیار هوشمند مدیریتی'],
        'messenger' => ['page' => Messenger::class, 'label' => 'پیام‌رسان', 'group' => 'ارتباطات داخلی'],
        'my-attendance' => ['page' => MyAttendance::class, 'label' => 'حضور و غیاب من', 'group' => 'حضور و غیاب'],
        'channel-manager-dashboard' => ['page' => ChannelManagerDashboard::class, 'label' => 'داشبورد کانال‌های فروش', 'group' => 'اقامتی و هتلداری'],
        'room-rack' => ['page' => RoomRack::class, 'label' => 'رک اتاق (وضعیت زنده)', 'group' => 'اقامتی و هتلداری'],
        'sales-pipeline' => ['page' => SalesPipeline::class, 'label' => 'پایپ‌لاین فروش (کانبان)', 'group' => 'CRM و مشتریان'],
    ],
];
