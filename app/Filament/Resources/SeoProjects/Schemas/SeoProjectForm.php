<?php

namespace App\Filament\Resources\SeoProjects\Schemas;

use App\Enums\SeoProjectGoal;
use App\Models\SeoProject;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class SeoProjectForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('اتصال Google Search Console')
                    ->visible(fn (?SeoProject $record) => $record !== null)
                    ->columns(2)
                    ->schema([
                        Placeholder::make('connection_status')
                            ->label('وضعیت اتصال')
                            ->content(fn (?SeoProject $record) => $record?->googleConnection?->status?->getLabel() ?? 'متصل نشده'),
                        Placeholder::make('connection_site')
                            ->label('ملک انتخاب‌شده')
                            ->content(fn (?SeoProject $record) => $record?->googleConnection?->gsc_site_url ?? '—'),
                        Placeholder::make('connection_last_sync')
                            ->label('آخرین همگام‌سازی')
                            ->content(fn (?SeoProject $record) => $record?->googleConnection?->last_synced_at?->translatedFormat('Y/m/d H:i') ?? '—'),
                        Placeholder::make('connection_error')
                            ->label('آخرین خطا')
                            ->visible(fn (?SeoProject $record) => filled($record?->googleConnection?->last_sync_error))
                            ->content(fn (?SeoProject $record) => new HtmlString('<span class="text-danger-600">'.e($record?->googleConnection?->last_sync_error).'</span>')),
                    ]),

                Section::make('اطلاعات کلی')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('نام پروژه')
                            ->helperText('مثلاً «گنجنامه - سایت اصلی» یا «تله‌کابین گنجنامه»، اگر بخش‌های مختلف دامنه/زیردامنه‌ی جدا دارند.')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('website_url')
                            ->label('آدرس وب‌سایت')
                            ->url()
                            ->required()
                            ->maxLength(255),
                        Select::make('goal')
                            ->label('هدف اصلی کسب‌وکار')
                            ->helperText('این روی محاسبه‌ی «ارزش تجاری» کلمات کلیدی در بخش هوشمندی سئو اثر می‌گذارد.')
                            ->options(SeoProjectGoal::class)
                            ->required()
                            ->native(false),
                        TextInput::make('market')
                            ->label('بازار هدف')
                            ->helperText('مثلاً «ایران» یا نام استان/شهر برای کسب‌وکارهای محلی.')
                            ->maxLength(255),
                        TextInput::make('brand')
                            ->label('نام برند')
                            ->maxLength(255),
                        Toggle::make('is_active')
                            ->label('فعال')
                            ->default(true),
                        Textarea::make('audience')
                            ->label('مخاطب هدف')
                            ->rows(2)
                            ->columnSpanFull(),
                        Textarea::make('real_services')
                            ->label('خدمات و محصولات واقعی')
                            ->helperText('دقیقاً بنویس چه خدمات/محصولاتی واقعاً ارائه می‌دهی و چه چیزهایی مرتبط با حوزه‌ی کاری‌ات هستند ولی نداری - مثال: «داریم: تله‌کابین، اجاره ویلا، تور غار علیصدر - نداریم: پیست اسکی، فروش سوغات». هوش مصنوعی موقع پیشنهاد کلمه‌ی کلیدی جدید فقط بر همین اساس قضاوت می‌کند.')
                            ->rows(3)
                            ->columnSpanFull(),
                        Textarea::make('notes')
                            ->label('یادداشت‌ها')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),

                Section::make('رقبا')
                    ->schema([
                        Repeater::make('competitors')
                            ->relationship()
                            ->hiddenLabel()
                            ->schema([
                                TextInput::make('domain')
                                    ->label('دامنه')
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('notes')
                                    ->label('یادداشت')
                                    ->maxLength(255),
                            ])
                            ->columns(2)
                            ->addActionLabel('افزودن رقیب')
                            ->defaultItems(0),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
