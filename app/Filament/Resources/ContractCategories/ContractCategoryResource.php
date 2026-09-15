<?php

namespace App\Filament\Resources\ContractCategories;

use App\Filament\Resources\ContractCategories\Pages\ManageContractCategories;
use App\Filament\Support\FieldHelp;
use App\Models\ContractCategory;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ContractCategoryResource extends Resource
{
    protected static ?string $model = ContractCategory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static string|\UnitEnum|null $navigationGroup = 'حقوقی و قرارداد';

    protected static ?string $modelLabel = 'دستهٔ قرارداد';

    protected static ?string $pluralModelLabel = 'دسته‌های قرارداد';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('نام دسته')
                    ->helperText('مثلاً «پیمانکاری برق»، «پشتیبانی سایت»، «اجاره»')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255)
                    ->hintAction(FieldHelp::make('contract-categories.name')),
                Textarea::make('ai_guidance')
                    ->label('راهنمای اختصاصی برای هوش مصنوعی (اختیاری)')
                    ->helperText('نکاتی که دستیار هوشمند هنگام نوشتن این نوع قرارداد باید همیشه رعایت کند، مثلاً بندهای فنی خاص.')
                    ->columnSpanFull()
                    ->hintAction(FieldHelp::make('contract-categories.ai_guidance')),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('نام دسته')->searchable(),
                TextColumn::make('contracts_count')->label('تعداد قراردادها')->counts('contracts')->numeric(),
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
            'index' => ManageContractCategories::route('/'),
        ];
    }
}
