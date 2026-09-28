<?php

namespace App\Filament\Resources\SeoContentItems\Schemas;

use App\Enums\SeoContentItemStatus;
use App\Models\SeoContentItem;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SeoContentItemForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('محتوا')
                    ->columns(2)
                    ->schema([
                        Select::make('seo_project_id')
                            ->label('پروژه')
                            ->relationship('project', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        TextInput::make('title')
                            ->label('عنوان')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        TextInput::make('target_keyword')
                            ->label('کلمه کلیدی هدف'),
                        TextInput::make('content_type')
                            ->label('نوع محتوا')
                            ->helperText('مثلاً «مقاله وبلاگ»، «صفحه فرود»، «بروزرسانی محتوای موجود».'),
                        Select::make('status')
                            ->label('وضعیت')
                            ->options(SeoContentItemStatus::class)
                            ->default(SeoContentItemStatus::Idea)
                            ->required()
                            ->native(false),
                        Select::make('assigned_to')
                            ->label('مسئول نگارش')
                            ->relationship('assignee', 'name')
                            ->searchable()
                            ->preload(),
                        DatePicker::make('due_date')
                            ->label('مهلت انجام')
                            ->jalali()
                            ->displayFormat('Y/m/d'),
                        TextInput::make('published_url')
                            ->label('آدرس نهایی منتشرشده')
                            ->url()
                            ->helperText('بعد از انتشار پر کنید - برای پیگیری بعدی عملکرد این محتوا در گزارش‌های سئو استفاده می‌شود.'),
                        DateTimePicker::make('published_at')
                            ->label('تاریخ انتشار')
                            ->jalali()
                            ->displayFormat('Y/m/d H:i'),
                        Textarea::make('notes')
                            ->label('یادداشت‌ها')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),

                Section::make('منشأ')
                    ->columns(2)
                    ->visible(fn (?SeoContentItem $record) => $record !== null
                        && ($record->keyword_profile_id || $record->keyword_suggestion_id || $record->seo_opportunity_id))
                    ->schema([
                        Select::make('keyword_profile_id')
                            ->label('کلمه کلیدی ردیابی‌شده')
                            ->relationship('keywordProfile', 'query')
                            ->disabled(),
                        Select::make('keyword_suggestion_id')
                            ->label('پیشنهاد کلمه کلیدی')
                            ->relationship('keywordSuggestion', 'keyword')
                            ->disabled(),
                        Select::make('seo_opportunity_id')
                            ->label('فرصت سئو')
                            ->relationship('opportunity', 'title')
                            ->disabled(),
                    ]),
            ]);
    }
}
