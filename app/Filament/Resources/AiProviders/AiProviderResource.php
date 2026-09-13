<?php

namespace App\Filament\Resources\AiProviders;

use App\Exceptions\AiProviderException;
use App\Filament\Resources\AiProviders\Pages\ManageAiProviders;
use App\Filament\Support\FieldHelp;
use App\Models\AiProvider;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AiProviderResource extends Resource
{
    protected static ?string $model = AiProvider::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|\UnitEnum|null $navigationGroup = 'دستیار هوشمند مدیریتی';

    protected static ?string $navigationLabel = 'تنظیمات هوش مصنوعی';

    protected static ?string $modelLabel = 'ارائه‌دهندهٔ هوش مصنوعی';

    protected static ?string $pluralModelLabel = 'ارائه‌دهندگان هوش مصنوعی';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('نام ارائه‌دهنده')
                    ->helperText('مثلاً «GapGPT» یا «OpenAI مستقیم»')
                    ->required()
                    ->maxLength(255)
                    ->hintAction(FieldHelp::make('ai-providers.name')),
                TextInput::make('base_url')
                    ->label('آدرس پایه (Base URL)')
                    ->url()
                    ->required()
                    ->placeholder('https://api.gapgpt.app/v1')
                    ->helperText('آدرس باید سازگار با OpenAI باشد (دارای مسیرهای /chat/completions و /models). آدرس دقیق را از مستندات ارائه‌دهندهٔ خود کپی کنید.')
                    ->maxLength(255)
                    ->hintAction(FieldHelp::make('ai-providers.base_url')),
                TextInput::make('api_key')
                    ->label('کلید API')
                    ->password()
                    ->revealable()
                    ->required()
                    ->maxLength(255)
                    ->hintAction(FieldHelp::make('ai-providers.api_key')),
                TextInput::make('default_model')
                    ->label('مدل پیش‌فرض')
                    ->required()
                    ->placeholder('gpt-4o-mini')
                    ->helperText('بعد از ذخیره، از دکمهٔ «انتخاب مدل از لیست» در جدول می‌توانید مدل دقیق را از فهرست مدل‌های این ارائه‌دهنده انتخاب کنید.')
                    ->maxLength(255)
                    ->hintAction(FieldHelp::make('ai-providers.default_model')),
                Toggle::make('is_active')
                    ->label('فعال (استفاده در دستیار هوشمند)')
                    ->helperText('در هر لحظه فقط یک ارائه‌دهنده می‌تواند فعال باشد؛ فعال‌کردن این یکی بقیه را غیرفعال می‌کند.')
                    ->hintAction(FieldHelp::make('ai-providers.is_active')),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('نام')->searchable(),
                TextColumn::make('base_url')->label('آدرس پایه')->limit(40),
                TextColumn::make('default_model')->label('مدل پیش‌فرض')->badge(),
                IconColumn::make('is_active')->label('فعال')->boolean(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                Action::make('pickModel')
                    ->label('انتخاب مدل از لیست')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->schema(fn (AiProvider $record) => [
                        Select::make('model')
                            ->label('مدل')
                            ->options(function () use ($record) {
                                try {
                                    $models = $record->listModels();

                                    return array_combine($models, $models);
                                } catch (AiProviderException $exception) {
                                    Notification::make()
                                        ->title('دریافت لیست مدل‌ها ناموفق بود')
                                        ->body($exception->getMessage())
                                        ->danger()
                                        ->send();

                                    return [];
                                }
                            })
                            ->searchable()
                            ->required()
                            ->default($record->default_model),
                    ])
                    ->action(function (array $data, AiProvider $record) {
                        $record->update(['default_model' => $data['model']]);

                        Notification::make()->title('مدل پیش‌فرض به‌روزرسانی شد.')->success()->send();
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
            'index' => ManageAiProviders::route('/'),
        ];
    }
}
