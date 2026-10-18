<?php

namespace App\Filament\Resources\SeoWpPosts;

use App\Filament\Resources\SeoWpPosts\Pages\ManageSeoWpPosts;
use App\Filament\Resources\SeoWpPosts\Tables\SeoWpPostsTable;
use App\Models\SeoWpPost;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * What the connector plugin reported about each page of the connected
 * WordPress sites, next to how it did in Search Console. Read-only: the data
 * comes from the sites, nothing is edited here.
 */
class SeoWpPostResource extends Resource
{
    protected static ?string $model = SeoWpPost::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentMagnifyingGlass;

    protected static string|\UnitEnum|null $navigationGroup = 'مرکز سئو';

    protected static ?string $navigationLabel = 'محتوای سایت';

    protected static ?string $modelLabel = 'محتوای سایت';

    protected static ?string $pluralModelLabel = 'محتوای سایت‌ها';

    protected static ?string $recordTitleAttribute = 'title';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return SeoWpPostsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSeoWpPosts::route('/'),
        ];
    }
}
