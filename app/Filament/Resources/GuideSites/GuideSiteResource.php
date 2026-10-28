<?php

namespace App\Filament\Resources\GuideSites;

use App\Filament\Resources\GuideSites\Pages\ManageGuideSites;
use App\Models\GuideSite;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Number;

class GuideSiteResource extends Resource
{
    protected static ?string $model = GuideSite::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    protected static string|\UnitEnum|null $navigationGroup = 'راهنمای هوشمند گردشگری';

    protected static ?string $navigationLabel = 'مجموعه‌های گردشگری';

    protected static ?string $modelLabel = 'مجموعهٔ گردشگری';

    protected static ?string $pluralModelLabel = 'مجموعه‌های گردشگری';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->label('نام مجموعه')->required()->maxLength(150),
                TextInput::make('slug')->label('شناسهٔ لاتین (اختیاری)')->maxLength(100)->unique(ignoreRecord: true)->alphaDash(),
                Toggle::make('is_active')->label('فعال')->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('نام')->searchable(),
                TextColumn::make('objects_count')->label('اشیا')->counts('objects')->formatStateUsing(fn ($state) => Number::toPersian((int) $state)),
                IconColumn::make('is_active')->label('فعال')->boolean(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageGuideSites::route('/')];
    }
}
