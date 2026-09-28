<?php

namespace App\Filament\Resources\SeoTrackedKeywords;

use App\Filament\Resources\SeoTrackedKeywords\Pages\CreateSeoTrackedKeyword;
use App\Filament\Resources\SeoTrackedKeywords\Pages\EditSeoTrackedKeyword;
use App\Filament\Resources\SeoTrackedKeywords\Pages\ListSeoTrackedKeywords;
use App\Filament\Resources\SeoTrackedKeywords\Schemas\SeoTrackedKeywordForm;
use App\Filament\Resources\SeoTrackedKeywords\Tables\SeoTrackedKeywordsTable;
use App\Models\SeoTrackedKeyword;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class SeoTrackedKeywordResource extends Resource
{
    protected static ?string $model = SeoTrackedKeyword::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'مرکز سئو';

    protected static ?string $navigationLabel = 'ترکر کلمه کلیدی';

    protected static ?string $modelLabel = 'کلمه کلیدی ردیابی‌شده';

    protected static ?string $pluralModelLabel = 'ترکر کلمه کلیدی';

    protected static ?string $recordTitleAttribute = 'keyword';

    public static function form(Schema $schema): Schema
    {
        return SeoTrackedKeywordForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SeoTrackedKeywordsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSeoTrackedKeywords::route('/'),
            'create' => CreateSeoTrackedKeyword::route('/create'),
            'edit' => EditSeoTrackedKeyword::route('/{record}/edit'),
        ];
    }
}
