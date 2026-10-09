<?php

namespace App\Filament\Resources\Attractions\Pages;

use App\Domain\TripPlanner\AttractionAiSuggestionService;
use App\Domain\TripPlanner\AttractionSuggestionException;
use App\Enums\AttractionCategory;
use App\Exceptions\AiProviderException;
use App\Filament\Resources\Attractions\AttractionResource;
use App\Models\Attraction;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Actions as FormActions;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;

class ListAttractions extends ListRecords
{
    protected static string $resource = AttractionResource::class;

    /**
     * See App\Domain\TripPlanner\AttractionAiSuggestionService - the "تولید
     * پیشنهاد" button inside this modal only fills the "suggestions"
     * repeater, it never submits; the admin reviews/edits every row
     * (deselecting, fixing a category, tweaking hours) and only the
     * modal's own submit button actually creates records - as drafts
     * (is_active = false, no lat/lng) so nothing reaches a real guest's
     * trip plan until an admin pins the exact location.
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('suggestAttractions')
                ->label('پیشنهاد جاذبه‌های جدید با هوش مصنوعی')
                ->icon(Heroicon::OutlinedSparkles)
                ->modalHeading('پیشنهاد جاذبه‌های جدید')
                ->modalDescription('هوش مصنوعی چند جاذبه‌ی واقعی و تکراری‌نشده پیشنهاد می‌دهد؛ قبل از ثبت می‌توانید هر مورد را ویرایش یا حذف کنید. مکان دقیق (روی نقشه) همیشه باید بعداً خودتان ثبت شود.')
                ->modalWidth('5xl')
                ->modalSubmitActionLabel('ایجاد موارد انتخاب‌شده')
                ->schema([
                    TextInput::make('focus')
                        ->label('تمرکز درخواستی (اختیاری)')
                        ->placeholder('مثلاً «روستاهای اطراف همدان» یا «جاهای مناسب خانواده»'),
                    TextInput::make('count')
                        ->label('تعداد پیشنهاد')
                        ->numeric()
                        ->default(5)
                        ->minValue(1)
                        ->maxValue(10)
                        ->required(),
                    FormActions::make([
                        Action::make('generateSuggestions')
                            ->label('تولید پیشنهاد')
                            ->icon(Heroicon::OutlinedLightBulb)
                            ->action(function (Get $get, Set $set) {
                                try {
                                    $items = app(AttractionAiSuggestionService::class)->suggestMany(
                                        (int) ($get('count') ?: 5),
                                        $get('focus') ?: null,
                                    );
                                } catch (AiProviderException|AttractionSuggestionException $e) {
                                    Notification::make()->title($e->getMessage())->danger()->send();

                                    return;
                                }

                                if (! $items) {
                                    Notification::make()
                                        ->title('هوش مصنوعی پیشنهاد تازه‌ای پیدا نکرد')
                                        ->body('ممکن است همه‌ی جاهای شناخته‌شده قبلاً ثبت شده باشند - تمرکز را عوض کنید یا دوباره تلاش کنید.')
                                        ->warning()
                                        ->send();

                                    return;
                                }

                                $set('suggestions', array_map(
                                    fn (array $item) => [...$item, 'import' => true],
                                    $items,
                                ));
                            }),
                    ]),
                    Repeater::make('suggestions')
                        ->label('پیش‌نمایش قابل‌ویرایش')
                        ->schema([
                            Toggle::make('import')
                                ->label('ثبت شود؟')
                                ->default(true),
                            TextInput::make('name')
                                ->label('نام')
                                ->required(),
                            Select::make('category')
                                ->label('دسته')
                                ->options(AttractionCategory::class)
                                ->required(),
                            TextInput::make('address')
                                ->label('آدرس'),
                            TextInput::make('avg_visit_minutes')
                                ->label('مدت بازدید (دقیقه)')
                                ->numeric()
                                ->required(),
                            TimePicker::make('opens_at')
                                ->label('ساعت باز شدن')
                                ->seconds(false),
                            TimePicker::make('closes_at')
                                ->label('ساعت بسته شدن')
                                ->seconds(false),
                            Textarea::make('description')
                                ->label('توضیحات')
                                ->columnSpanFull(),
                        ])
                        ->columns(3)
                        ->itemLabel(fn (array $state): ?string => $state['name'] ?? null)
                        ->addable(false)
                        ->reorderable(false)
                        ->default([])
                        ->visible(fn (Get $get): bool => filled($get('suggestions'))),
                ])
                ->action(function (array $data): void {
                    $created = 0;
                    $skipped = 0;

                    foreach ($data['suggestions'] ?? [] as $row) {
                        if (! ($row['import'] ?? false) || blank($row['name'] ?? null)) {
                            continue;
                        }

                        if (Attraction::where('name', $row['name'])->exists()) {
                            $skipped++;

                            continue;
                        }

                        Attraction::create([
                            'name' => $row['name'],
                            'category' => $row['category'],
                            'description' => $row['description'] ?: null,
                            'address' => $row['address'] ?: null,
                            'avg_visit_minutes' => $row['avg_visit_minutes'] ?: 60,
                            'opens_at' => $row['opens_at'] ?: null,
                            'closes_at' => $row['closes_at'] ?: null,
                            'is_active' => false,
                        ]);

                        $created++;
                    }

                    if ($created === 0) {
                        Notification::make()
                            ->title('هیچ موردی ثبت نشد')
                            ->warning()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title("{$created} جاذبه به‌صورت پیش‌نویس ثبت شد")
                        ->body('این موارد غیرفعال هستند - برای هرکدام مختصات دقیق را روی نقشه ثبت کنید و سپس فعالشان کنید.'.($skipped ? " ({$skipped} مورد به‌دلیل هم‌نامی با جاذبه‌ی موجود رد شد.)" : ''))
                        ->success()
                        ->send();
                }),
            CreateAction::make(),
        ];
    }
}
