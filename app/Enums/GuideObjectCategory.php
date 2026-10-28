<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum GuideObjectCategory: string implements HasLabel
{
    case Inscription = 'inscription';
    case PrimitiveManStatue = 'primitive_man_statue';
    case Statue3d = 'statue_3d';
    case Taxidermy = 'taxidermy';
    case HistoricalObject = 'historical_object';
    case MuseumPiece = 'museum_piece';
    case Aquatic = 'aquatic';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Inscription => 'کتیبهٔ تاریخی',
            self::PrimitiveManStatue => 'مجسمهٔ انسان نخستین',
            self::Statue3d => 'مجسمهٔ سه‌بعدی',
            self::Taxidermy => 'حیوان تاکسیدرمی‌شده',
            self::HistoricalObject => 'شیء تاریخی',
            self::MuseumPiece => 'اثر موزه‌ای',
            self::Aquatic => 'آکواریوم و آبزیان',
            self::Other => 'سایر',
        };
    }
}
