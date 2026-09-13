<?php

namespace App\Filament\Resources\Departments;

use App\Filament\Resources\Departments\Pages\ManageDepartments;
use App\Filament\Support\FieldHelp;
use App\Models\Department;
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

class DepartmentResource extends Resource
{
    protected static ?string $model = Department::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'زیرساخت سازمانی';

    protected static ?string $modelLabel = 'دپارتمان';

    protected static ?string $pluralModelLabel = 'دپارتمان‌ها';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('نام دپارتمان')
                    ->required()
                    ->maxLength(255)
                    ->hintAction(FieldHelp::make('departments.name')),
                TextInput::make('code')
                    ->label('کد دپارتمان')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(50)
                    ->hintAction(FieldHelp::make('departments.code')),
                Select::make('parent_id')
                    ->label('زیرمجموعه‌ی دپارتمان')
                    ->relationship('parent', 'name')
                    ->searchable()
                    ->preload()
                    ->hintAction(FieldHelp::make('departments.parent_id')),
                Select::make('manager_id')
                    ->label('مدیر دپارتمان')
                    ->relationship('manager', 'name')
                    ->searchable()
                    ->preload()
                    ->helperText('در گردش‌کارهای تأیید، درخواست‌های این دپارتمان برای تأیید به همین شخص می‌رسد.')
                    ->hintAction(FieldHelp::make('departments.manager_id')),
                Select::make('company_id')
                    ->label('شرکت (اختیاری)')
                    ->relationship('company', 'name')
                    ->searchable()
                    ->preload()
                    ->helperText('برای سازمان‌های هلدینگ: هر شرکت زیرمجموعه را یک‌بار اینجا انتخاب کنید تا اسناد/پروژه‌های این دپارتمان خودکار به همین شرکت نسبت داده شوند.')
                    ->hintAction(FieldHelp::make('departments.company_id')),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('نام')->searchable(),
                TextColumn::make('code')->label('کد')->badge(),
                TextColumn::make('parent.name')->label('زیرمجموعه‌ی')->placeholder('—'),
                TextColumn::make('company.name')->label('شرکت')->placeholder('—'),
                TextColumn::make('users_count')->label('تعداد پرسنل')->counts('users'),
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
            'index' => ManageDepartments::route('/'),
        ];
    }
}
