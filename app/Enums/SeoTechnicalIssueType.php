<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum SeoTechnicalIssueType: string implements HasLabel
{
    case BrokenLink = 'broken_link';
    case MissingTitle = 'missing_title';
    case DuplicateTitle = 'duplicate_title';
    case MissingMetaDescription = 'missing_meta_description';
    case MissingAlt = 'missing_alt';
    case SlowPage = 'slow_page';
    case MissingRobots = 'missing_robots';
    case MissingSitemap = 'missing_sitemap';
    case MissingStructuredData = 'missing_structured_data';
    case OrphanPage = 'orphan_page';
    case InconsistentPhoneNumber = 'inconsistent_phone_number';
    case MissingLocalBusinessSchema = 'missing_local_business_schema';
    case MissingH1 = 'missing_h1';
    case MultipleH1 = 'multiple_h1';
    case DuplicateH1 = 'duplicate_h1';
    case MissingCanonical = 'missing_canonical';
    case MissingViewport = 'missing_viewport';
    case MissingOpenGraph = 'missing_open_graph';
    case RedirectChain = 'redirect_chain';
    case ImageMissingDimensions = 'image_missing_dimensions';
    case ThinContent = 'thin_content';
    case DuplicateContent = 'duplicate_content';

    public function getLabel(): string
    {
        return match ($this) {
            self::BrokenLink => 'لینک خراب',
            self::MissingTitle => 'بدون عنوان (title)',
            self::DuplicateTitle => 'عنوان تکراری',
            self::MissingMetaDescription => 'بدون توضیحات متا',
            self::MissingAlt => 'تصویر بدون متن جایگزین (alt)',
            self::SlowPage => 'صفحهٔ کند',
            self::MissingRobots => 'فایل robots.txt یافت نشد',
            self::MissingSitemap => 'نقشهٔ سایت (sitemap.xml) یافت نشد',
            self::MissingStructuredData => 'بدون داده‌ی ساخت‌یافته (Schema/JSON-LD)',
            self::OrphanPage => 'صفحهٔ یتیم (بدون لینک داخلی)',
            self::InconsistentPhoneNumber => 'شماره تماس ناهماهنگ در سایت',
            self::MissingLocalBusinessSchema => 'بدون Schema کسب‌وکار محلی (LocalBusiness)',
            self::MissingH1 => 'بدون سرتیتر (H1)',
            self::MultipleH1 => 'چند سرتیتر (H1) روی یک صفحه',
            self::DuplicateH1 => 'سرتیتر (H1) تکراری',
            self::MissingCanonical => 'بدون تگ canonical',
            self::MissingViewport => 'بدون متا تگ viewport (ریسپانسیو نبودن)',
            self::MissingOpenGraph => 'بدون تگ‌های Open Graph (پیش‌نمایش ضعیف در واتساپ/شبکه‌های اجتماعی)',
            self::RedirectChain => 'زنجیره‌ی ریدایرکت طولانی',
            self::ImageMissingDimensions => 'تصویر بدون ابعاد مشخص (width/height)',
            self::ThinContent => 'محتوای کم‌حجم',
            self::DuplicateContent => 'محتوای تکراری',
        };
    }
}
