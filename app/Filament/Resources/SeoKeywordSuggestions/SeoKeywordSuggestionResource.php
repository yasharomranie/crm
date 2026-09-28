<?php

namespace App\Filament\Resources\SeoKeywordSuggestions;

use App\Filament\Resources\SeoKeywordSuggestions\Pages\ManageSeoKeywordSuggestions;
use App\Filament\Resources\SeoKeywordSuggestions\Tables\SeoKeywordSuggestionsTable;
use App\Models\SeoKeywordSuggestion;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * AI-generated ("کشف کلمات کلیدی جدید" action, App\Domain\Seo\AiKeywordDiscoveryService)
 * - a review workflow (approve/reject), never a create form: these are
 * hypotheses for a human to validate, not data to hand-enter here.
 */
class SeoKeywordSuggestionResource extends Resource
{
    protected static ?string $model = SeoKeywordSuggestion::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static string|\UnitEnum|null $navigationGroup = 'مرکز سئو';

    protected static ?string $navigationLabel = 'پیشنهادهای کلمات کلیدی';

    protected static ?string $modelLabel = 'پیشنهاد کلمه کلیدی';

    protected static ?string $pluralModelLabel = 'پیشنهادهای کلمات کلیدی';

    protected static ?string $recordTitleAttribute = 'keyword';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return SeoKeywordSuggestionsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSeoKeywordSuggestions::route('/'),
        ];
    }
}
