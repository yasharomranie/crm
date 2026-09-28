<?php

namespace App\Filament\Resources\WebAppBanners;

use App\Filament\Resources\WebAppBanners\Pages\ManageWebAppBanners;
use App\Filament\Resources\WebAppBanners\Schemas\WebAppBannerForm;
use App\Filament\Resources\WebAppBanners\Tables\WebAppBannersTable;
use App\Models\WebAppBanner;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class WebAppBannerResource extends Resource
{
    protected static ?string $model = WebAppBanner::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static string|\UnitEnum|null $navigationGroup = 'وب‌اپ مشتریان';

    protected static ?string $modelLabel = 'بنر تبلیغاتی';

    protected static ?string $pluralModelLabel = 'بنرهای تبلیغاتی';

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return WebAppBannerForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WebAppBannersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageWebAppBanners::route('/'),
        ];
    }
}
