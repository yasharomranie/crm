<?php

namespace App\Filament\Resources\DealAutomationRules;

use App\Enums\DealStage;
use App\Filament\Resources\DealAutomationRules\Pages\ManageDealAutomationRules;
use App\Models\DealAutomationRule;
use App\Models\User;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Number;

class DealAutomationRuleResource extends Resource
{
    protected static ?string $model = DealAutomationRule::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBolt;

    protected static string|\UnitEnum|null $navigationGroup = 'CRM و مشتریان';

    protected static ?string $modelLabel = 'قانون اتوماسیون معامله';

    protected static ?string $pluralModelLabel = 'اتوماسیون پایپ‌لاین';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('نام قانون')
                    ->helperText('فقط برای شما، مثلاً «تماس سریع با سرنخ جدید»')
                    ->required()
                    ->maxLength(255),
                Select::make('trigger_stage')
                    ->label('وقتی معامله وارد این مرحله شد')
                    ->helperText('هم برای معامله‌ی تازه‌ساخته‌شده در این مرحله اجرا می‌شود (مثلاً از فرم جذب سرنخ)، هم وقتی معامله‌ای به آن منتقل شود.')
                    ->options(DealStage::class)
                    ->required(),
                TextInput::make('task_title')
                    ->label('عنوان پیگیری خودکار')
                    ->helperText('می‌توانید از {deal} برای درج عنوان معامله استفاده کنید. مثلاً «تماس برای {deal}»')
                    ->default('پیگیری {deal}')
                    ->required()
                    ->maxLength(255),
                TextInput::make('due_after_hours')
                    ->label('مهلت پیگیری (ساعت بعد)')
                    ->numeric()
                    ->minValue(0)
                    ->default(24)
                    ->required(),
                Select::make('assigned_to')
                    ->label('مسئول پیگیری')
                    ->helperText('اگر خالی بماند، مسئول خودِ معامله انجام می‌دهد؛ معامله‌ی بدون مسئول، پیگیری بدون مسئول می‌سازد و یادآوری نمی‌شود.')
                    ->options(fn () => User::query()->orderBy('name')->pluck('name', 'id'))
                    ->searchable(),
                Toggle::make('is_active')
                    ->label('فعال')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('نام قانون')->searchable(),
                TextColumn::make('trigger_stage')->label('مرحله‌ی شروع')->badge(),
                TextColumn::make('task_title')->label('پیگیری')->limit(40),
                TextColumn::make('due_after_hours')
                    ->label('مهلت')
                    ->formatStateUsing(fn (int $state) => Number::toPersian($state).' ساعت'),
                TextColumn::make('assignee.name')->label('مسئول')->placeholder('مسئول معامله'),
                IconColumn::make('is_active')->label('فعال')->boolean(),
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
            'index' => ManageDealAutomationRules::route('/'),
        ];
    }
}
