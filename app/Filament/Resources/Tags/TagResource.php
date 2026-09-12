<?php

namespace App\Filament\Resources\Tags;

use App\Enums\TagVisibility;
use App\Filament\Resources\Tags\Pages\ManageTags;
use App\Models\Tag;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TagResource extends Resource
{
    protected static ?string $model = Tag::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static string|\UnitEnum|null $navigationGroup = 'زیرساخت سازمانی';

    protected static ?string $modelLabel = 'برچسب';

    protected static ?string $pluralModelLabel = 'برچسب‌ها (تگ‌ها)';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('نام برچسب')
                    ->required()
                    ->maxLength(255),
                Select::make('parent_id')
                    ->label('برچسب والد (خوشه)')
                    ->relationship('parent', 'name')
                    ->searchable()
                    ->preload(),
                Select::make('visibility')
                    ->label('نوع برچسب')
                    ->options(TagVisibility::class)
                    ->default(TagVisibility::System)
                    ->required(),
                ColorPicker::make('color_hex')
                    ->label('رنگ نشانگر'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('نام')->searchable(),
                ColorColumn::make('color_hex')->label('رنگ'),
                TextColumn::make('visibility')->label('نوع')->badge(),
                TextColumn::make('parent.name')->label('خوشه')->placeholder('—'),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageTags::route('/'),
        ];
    }
}
