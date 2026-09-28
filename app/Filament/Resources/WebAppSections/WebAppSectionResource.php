<?php

namespace App\Filament\Resources\WebAppSections;

use App\Filament\Resources\WebAppSections\Pages\ManageWebAppSections;
use App\Filament\Resources\WebAppSections\Schemas\WebAppSectionForm;
use App\Filament\Resources\WebAppSections\Tables\WebAppSectionsTable;
use App\Models\WebAppSection;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class WebAppSectionResource extends Resource
{
    protected static ?string $model = WebAppSection::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static string|\UnitEnum|null $navigationGroup = 'وب‌اپ مشتریان';

    protected static ?string $modelLabel = 'بخش صفحه‌ی اپ';

    protected static ?string $pluralModelLabel = 'بخش‌های صفحه‌ی اپ';

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return WebAppSectionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WebAppSectionsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageWebAppSections::route('/'),
        ];
    }
}
