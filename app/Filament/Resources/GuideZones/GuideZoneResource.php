<?php

namespace App\Filament\Resources\GuideZones;

use App\Filament\Resources\GuideZones\Pages\ManageGuideZones;
use App\Models\GuideSite;
use App\Models\GuideZone;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class GuideZoneResource extends Resource
{
    protected static ?string $model = GuideZone::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static string|\UnitEnum|null $navigationGroup = 'راهنمای هوشمند گردشگری';

    protected static ?string $navigationLabel = 'فضاها (تالار و بخش)';

    protected static ?string $modelLabel = 'فضا';

    protected static ?string $pluralModelLabel = 'فضاها';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('guide_site_id')->label('مجموعه')->relationship('site', 'name')->default(fn () => GuideSite::query()->value('id'))->required(),
                TextInput::make('name')->label('نام فضا')->required()->maxLength(150),
                TextInput::make('sort_order')->label('ترتیب')->numeric()->integer()->minValue(0)->default(0),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('site.name')->label('مجموعه'),
                TextColumn::make('name')->label('فضا')->searchable(),
                TextColumn::make('sort_order')->label('ترتیب')->sortable(),
            ])
            ->defaultSort('sort_order')
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageGuideZones::route('/')];
    }
}
