<?php

namespace App\Filament\Resources\WebAppSections\Schemas;

use App\Enums\WebAppTileAccent;
use App\Models\WebAppSection as WebAppSectionModel;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class WebAppSectionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->label('سرتیتر بخش')
                    ->required()
                    ->maxLength(255),
                TextInput::make('sort_order')
                    ->label('ترتیب نمایش بخش')
                    ->numeric()
                    ->default(fn () => WebAppSectionModel::max('sort_order') + 1)
                    ->required(),
                Toggle::make('is_active')
                    ->label('نمایش در اپ')
                    ->default(true),

                Section::make('آیتم‌های این بخش')
                    ->schema([
                        Repeater::make('items')
                            ->relationship()
                            ->hiddenLabel()
                            ->schema([
                                // ->disk('public'): shown directly on the
                                // public app page, same reasoning as
                                // WebAppBannerForm's banner image.
                                FileUpload::make('icon_path')
                                    ->label('آیکون')
                                    ->image()
                                    ->disk('public')
                                    ->directory('web-app/section-icons')
                                    ->helperText('ترجیحاً تصویر ساده و تک‌رنگ روی زمینه‌ی شفاف.'),
                                TextInput::make('label')
                                    ->label('عنوان آیتم')
                                    ->required()
                                    ->maxLength(255),
                                Select::make('accent')
                                    ->label('رنگ پس‌زمینه')
                                    ->options(WebAppTileAccent::class)
                                    ->default(WebAppTileAccent::Orange)
                                    ->native(false)
                                    ->required(),
                                TextInput::make('link_url')
                                    ->label('لینک (اختیاری)')
                                    ->url()
                                    ->maxLength(255),
                                TextInput::make('sort_order')
                                    ->label('ترتیب')
                                    ->numeric()
                                    ->default(fn (?WebAppSectionModel $record) => ($record?->items()->max('sort_order') ?? -1) + 1)
                                    ->required(),
                                Toggle::make('is_active')
                                    ->label('نمایش در اپ')
                                    ->default(true),
                            ])
                            ->columns(2)
                            ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
                            ->addActionLabel('افزودن آیتم')
                            ->defaultItems(0),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
