<?php

namespace App\Filament\Resources\Companies;

use App\Filament\Resources\Companies\Pages\ManageCompanies;
use App\Filament\Support\FieldHelp;
use App\Models\Company;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * The holding's central "شرکت‌ها" registry - one place to define every
 * company under the holding (e.g. گنج‌نامه، زاگرس، هتل بوتیک اوا), reused
 * as the "کارفرما" side of contracts and as the company a department
 * belongs to.
 */
class CompanyResource extends Resource
{
    protected static ?string $model = Company::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static string|\UnitEnum|null $navigationGroup = 'زیرساخت سازمانی';

    protected static ?string $modelLabel = 'شرکت';

    protected static ?string $pluralModelLabel = 'شرکت‌های هلدینگ';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('نام شرکت')
                    ->required()
                    ->maxLength(255)
                    ->hintAction(FieldHelp::make('companies.name')),
                TextInput::make('national_id')
                    ->label('شناسه ملی / کد ملی')
                    ->maxLength(255)
                    ->hintAction(FieldHelp::make('companies.national_id')),
                TextInput::make('economic_code')
                    ->label('کد اقتصادی')
                    ->maxLength(255)
                    ->hintAction(FieldHelp::make('companies.economic_code')),
                TextInput::make('address')
                    ->label('نشانی')
                    ->columnSpanFull()
                    ->maxLength(255)
                    ->hintAction(FieldHelp::make('companies.address')),
                TextInput::make('representative_name')
                    ->label('نام نمایندهٔ قانونی')
                    ->maxLength(255)
                    ->hintAction(FieldHelp::make('companies.representative_name')),
                TextInput::make('representative_role')
                    ->label('سمت نماینده')
                    ->maxLength(255)
                    ->hintAction(FieldHelp::make('companies.representative_role')),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('نام شرکت')->searchable(),
                TextColumn::make('national_id')->label('شناسه ملی')->placeholder('—'),
                TextColumn::make('representative_name')->label('نماینده')->placeholder('—'),
                TextColumn::make('departments_count')->label('تعداد دپارتمان‌ها')->counts('departments')->numeric(),
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
            'index' => ManageCompanies::route('/'),
        ];
    }
}
