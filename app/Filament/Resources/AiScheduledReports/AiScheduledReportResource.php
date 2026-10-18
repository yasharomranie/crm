<?php

namespace App\Filament\Resources\AiScheduledReports;

use App\Filament\Resources\AiScheduledReports\Pages\ManageAiScheduledReports;
use App\Jobs\RunAiScheduledReportJob;
use App\Models\AiScheduledReport;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * A manager's own list of questions the management assistant answers every
 * day by itself. Everyone sees and edits only their own.
 */
class AiScheduledReportResource extends Resource
{
    protected static ?string $model = AiScheduledReport::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|\UnitEnum|null $navigationGroup = 'دستیار هوشمند مدیریتی';

    protected static ?string $navigationLabel = 'گزارش‌های زمان‌بندی‌شده';

    protected static ?string $modelLabel = 'گزارش زمان‌بندی‌شده';

    protected static ?string $pluralModelLabel = 'گزارش‌های زمان‌بندی‌شدهٔ دستیار';

    protected static ?string $recordTitleAttribute = 'prompt';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('user_id', Auth::id());
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Textarea::make('prompt')
                    ->label('سؤال')
                    ->placeholder('مثلاً: امروز چه کسانی هنوز ورود نزده‌اند و کدام وظایف عقب افتاده‌اند؟')
                    ->helperText('دستیار هر روز در ساعت تعیین‌شده این سؤال را با دسترسی‌های خود شما (فقط ابزارهای گزارش‌گیری) جواب می‌دهد و پاسخ را در یک گفتگوی جدید برایتان می‌گذارد.')
                    ->required()
                    ->rows(3)
                    ->maxLength(1000),
                TextInput::make('run_at_time')
                    ->label('ساعت اجرا (به وقت تهران)')
                    ->default('08:00')
                    ->required()
                    ->regex('/^([01]\d|2[0-3]):[0-5]\d$/')
                    ->placeholder('08:00')
                    ->helperText('با قالب ۲۴ ساعته، مثلاً 08:00 یا 17:30. اجرا در بازه‌های ۱۵ دقیقه‌ای انجام می‌شود.'),
                Toggle::make('is_active')
                    ->label('فعال')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('prompt')->label('سؤال')->limit(60)->wrap(),
                TextColumn::make('run_at_time')->label('ساعت')->badge(),
                IconColumn::make('is_active')->label('فعال')->boolean(),
                TextColumn::make('last_run_at')->label('آخرین اجرا')->jalaliDateTime('Y/m/d H:i')->placeholder('—'),
            ])
            ->recordActions([
                Action::make('runNow')
                    ->label('اجرای همین حالا')
                    ->icon(Heroicon::OutlinedPlay)
                    ->requiresConfirmation()
                    ->action(function (AiScheduledReport $record) {
                        RunAiScheduledReportJob::dispatch($record->id);

                        Notification::make()
                            ->title('اجرا در صف قرار گرفت')
                            ->body('نتیجه تا چند دقیقهٔ دیگر به‌صورت یک گفتگوی جدید در دستیار هوشمند ظاهر می‌شود.')
                            ->success()
                            ->send();
                    }),
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
            'index' => ManageAiScheduledReports::route('/'),
        ];
    }
}
