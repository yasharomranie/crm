<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * What kind of work a finished action or a "done" journal line was. The
 * management report counts finished work per category and week.
 */
enum DigitalProjectWorkCategory: string implements HasLabel
{
    case VisualImprovement = 'visual_improvement';
    case Coding = 'coding';
    case Security = 'security';
    case Seo = 'seo';
    case Articles = 'articles';
    case ContentProduction = 'content_production';
    case IndexMonitoring = 'index_monitoring';

    public function getLabel(): string
    {
        return match ($this) {
            self::VisualImprovement => 'بهبود بصری',
            self::Coding => 'کدنویسی',
            self::Security => 'امنیت',
            self::Seo => 'سئو',
            self::Articles => 'مقالات',
            self::ContentProduction => 'تولید محتوا',
            self::IndexMonitoring => 'نظارت ایندکسی',
        };
    }

    /** The report's column group this category belongs to: «توسعه» or «عملکرد». */
    public function group(): string
    {
        return match ($this) {
            self::VisualImprovement, self::Coding, self::Security => 'توسعه',
            self::Seo, self::Articles, self::ContentProduction, self::IndexMonitoring => 'عملکرد',
        };
    }
}
