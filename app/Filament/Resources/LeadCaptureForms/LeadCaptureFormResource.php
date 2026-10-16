<?php

namespace App\Filament\Resources\LeadCaptureForms;

use App\Enums\DealSource;
use App\Filament\Resources\LeadCaptureForms\Pages\ManageLeadCaptureForms;
use App\Models\LeadCaptureForm;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class LeadCaptureFormResource extends Resource
{
    protected static ?string $model = LeadCaptureForm::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static string|\UnitEnum|null $navigationGroup = 'CRM و مشتریان';

    protected static ?string $modelLabel = 'فرم جذب سرنخ';

    protected static ?string $pluralModelLabel = 'فرم‌های جذب سرنخ';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('نام داخلی فرم')
                    ->helperText('فقط برای شما: مثلاً «لندینگ‌پیج تور نوروزی». در عنوان معامله‌های ساخته‌شده هم می‌آید.')
                    ->required()
                    ->maxLength(255),
                TextInput::make('slug')
                    ->label('بخش انتهایی آدرس')
                    ->helperText('فقط حروف انگلیسی، عدد و خط تیره. آدرس عمومی فرم: /lead/این-بخش')
                    ->default(fn () => Str::lower(Str::random(8)))
                    ->required()
                    ->alphaDash()
                    ->maxLength(100)
                    ->unique(ignoreRecord: true),
                TextInput::make('title')
                    ->label('عنوان نمایش‌داده‌شده به بازدیدکننده')
                    ->required()
                    ->maxLength(255),
                Textarea::make('description')
                    ->label('توضیح زیر عنوان (اختیاری)')
                    ->rows(2)
                    ->maxLength(1000),
                Textarea::make('success_message')
                    ->label('پیام بعد از ثبت موفق')
                    ->default('درخواست شما ثبت شد. به‌زودی با شما تماس می‌گیریم.')
                    ->required()
                    ->rows(2)
                    ->maxLength(1000),
                Select::make('deal_source')
                    ->label('منبع معامله‌های ساخته‌شده')
                    ->options(DealSource::class)
                    ->default(DealSource::Online)
                    ->required(),
                Select::make('assigned_to')
                    ->label('ارجاع خودکار به')
                    ->helperText('اگر خالی بماند، معامله‌ها بدون مسئول ساخته می‌شوند.')
                    ->options(fn () => User::query()->orderBy('name')->pluck('name', 'id'))
                    ->searchable(),
                Toggle::make('is_active')
                    ->label('فعال')
                    ->helperText('با غیرفعال کردن، آدرس عمومی فرم خطای «یافت نشد» می‌دهد.')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('نام فرم')->searchable(),
                TextColumn::make('slug')
                    ->label('لینک عمومی')
                    ->formatStateUsing(fn (string $state) => route('lead-capture.show', $state))
                    ->copyable()
                    ->copyMessage('لینک کپی شد')
                    ->limit(40),
                TextColumn::make('deals_count')->label('سرنخ‌های ثبت‌شده')->counts('deals')->numeric(),
                IconColumn::make('is_active')->label('فعال')->boolean(),
            ])
            ->recordActions([
                Action::make('embedCode')
                    ->label('کد جاسازی')
                    ->icon('heroicon-o-code-bracket')
                    ->color('gray')
                    ->fillForm(fn (LeadCaptureForm $record) => [
                        'code' => '<iframe src="'.$record->publicUrl().'" width="100%" height="520" style="border:0;" loading="lazy"></iframe>',
                    ])
                    ->schema([
                        Textarea::make('code')
                            ->label('این کد را در صفحه‌ی سایت خود قرار دهید')
                            ->readOnly()
                            ->rows(4),
                    ])
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('بستن'),
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
            'index' => ManageLeadCaptureForms::route('/'),
        ];
    }
}
