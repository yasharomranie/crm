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
        };
    }
}
