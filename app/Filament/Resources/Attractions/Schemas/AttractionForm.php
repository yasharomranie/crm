<?php

namespace App\Filament\Resources\Attractions\Schemas;

use App\Enums\AttractionCategory;
use App\Models\Attraction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class AttractionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('اطلاعات اصلی')
                    ->columns(2)
                    ->components([
                        TextInput::make('name')
                            ->label('نام جاذبه')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn ($state, $set) => $set('slug', Str::slug($state))),
                        TextInput::make('slug')
                            ->label('نامک (Slug)')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        Select::make('category')
                            ->label('دسته')
                            ->options(AttractionCategory::class)
                            ->required(),
                        TextInput::make('address')
                            ->label('آدرس')
                            ->maxLength(255),
                        Textarea::make('description')
                            ->label('توضیحات')
                            ->columnSpanFull(),
                        FileUpload::make('image')
                            ->label('تصویر')
                            ->image()
                            ->disk('public')
                            ->directory('attractions')
                            ->imageEditor()
                            ->columnSpanFull(),
                    ]),

                Section::make('موقعیت و زمان‌بندی')
                    ->columns(2)
                    ->components([
                        TextInput::make('lat')
                            ->label('عرض جغرافیایی (lat)')
                            ->required(fn (Get $get): bool => (bool) $get('is_active'))
                            ->numeric()
                            ->step(0.0000001)
                            ->helperText('تا وقتی خالی است، جاذبه پیش‌نویس می‌ماند و «فعال» را نمی‌توان روشن کرد.'),
                        TextInput::make('lng')
                            ->label('طول جغرافیایی (lng)')
                            ->required(fn (Get $get): bool => (bool) $get('is_active'))
                            ->numeric()
                            ->step(0.0000001)
                            ->helperText('مختصات را می‌توانید از روی Google Maps (راست‌کلیک روی نقطه) کپی کنید.'),
                        TextInput::make('avg_visit_minutes')
                            ->label('مدت متوسط بازدید (دقیقه)')
                            ->required()
                            ->numeric()
                            ->default(60),
                        TimePicker::make('opens_at')
                            ->label('ساعت باز شدن')
                            ->seconds(false),
                        TimePicker::make('closes_at')
                            ->label('ساعت بسته شدن')
                            ->seconds(false),
                    ]),

                Section::make('جزئیات برای مهمان و برنامه‌ریز')
                    ->description('هرچه این اطلاعات کامل‌تر باشد، برنامه‌ی ساخته‌شده دقیق‌تر و به نیاز مهمان نزدیک‌تر است.')
                    ->columns(2)
                    ->components([
                        CheckboxList::make('closed_weekdays')
                            ->label('روزهای تعطیل هفتگی')
                            ->options(Attraction::WEEKDAY_LABELS)
                            ->columns(4)
                            ->columnSpanFull(),
                        Repeater::make('weekday_hours')
                            ->label('ساعت کاری ویژه‌ی بعضی روزها')
                            ->helperText('فقط برای روزهایی که ساعتشان با ساعت بالا فرق دارد (مثلاً جمعه‌ها دیرتر باز می‌شود). روز دیگر همان ساعت بالا را دارد.')
                            ->schema([
                                Select::make('weekday')
                                    ->label('روز')
                                    ->options(Attraction::WEEKDAY_LABELS)
                                    ->required()
                                    ->distinct()
                                    ->native(false),
                                TimePicker::make('opens_at')->label('باز می‌شود')->seconds(false)->required(),
                                TimePicker::make('closes_at')->label('بسته می‌شود')->seconds(false)->required(),
                            ])
                            ->columns(3)
                            ->addActionLabel('افزودن روز')
                            ->defaultItems(0)
                            ->columnSpanFull(),
                        TextInput::make('entry_fee')
                            ->label('هزینه‌ی ورودی (تومان)')
                            ->helperText('خالی = نامشخص، ۰ = رایگان.')
                            ->numeric()
                            ->minValue(0),
                        TextInput::make('phone')
                            ->label('تلفن تماس')
                            ->maxLength(50),
                        Toggle::make('kid_friendly')->label('مناسب کودک'),
                        Toggle::make('senior_friendly')->label('مناسب سالمند'),
                        Toggle::make('has_parking')->label('پارکینگ دارد'),
                        Select::make('product_id')
                            ->label('محصول بلیت مرتبط (برای دکمه‌ی خرید بلیت)')
                            ->relationship('product', 'name')
                            ->searchable()
                            ->preload(),
                        Textarea::make('best_time_note')
                            ->label('بهترین زمان بازدید')
                            ->helperText('مثلاً «غروب» یا «بهار». برای مهمان و برنامه‌ریز نمایش داده می‌شود.')
                            ->rows(2)
                            ->columnSpanFull(),
                        Placeholder::make('source_info')
                            ->label('منبع')
                            ->content(fn (?Attraction $record) => match (true) {
                                $record === null => 'ثبت دستی',
                                $record->source === 'ai_suggested' && $record->verified_at === null => 'پیشنهاد هوش مصنوعی - هنوز بازبینی نشده',
                                $record->source === 'ai_suggested' => 'پیشنهاد هوش مصنوعی - بازبینی‌شده',
                                default => 'ثبت دستی',
                            })
                            ->columnSpanFull(),
                        Toggle::make('verified')
                            ->label('اطلاعات این جاذبه را بازبینی و تأیید کردم')
                            ->helperText('برای جاذبه‌های پیشنهادی هوش مصنوعی: نام، توضیح و ساعت‌ها را با منبع معتبر مقایسه کنید.')
                            ->afterStateHydrated(fn (Toggle $component, ?Attraction $record) => $component->state($record?->verified_at !== null))
                            ->dehydrated(false)
                            ->visible(fn (?Attraction $record) => $record?->source === 'ai_suggested')
                            ->columnSpanFull(),
                    ]),

                Section::make('دخالت و اولویت‌دهی ادمین')
                    ->description('این بخش مستقیماً روی پیشنهادهای دستیار هوشمند تریپ‌پلنر اثر می‌گذارد - بدون نیاز به دیپلوی.')
                    ->columns(2)
                    ->components([
                        Select::make('boost_weight')
                            ->label('اولویت در پیشنهاد')
                            ->options([
                                '-2' => 'کم‌اهمیت (کمتر پیشنهاد شود)',
                                '0' => 'عادی',
                                '1' => 'مهم (بیشتر پیشنهاد شود)',
                                '2' => 'ویژه (در اولویت اول)',
                            ])
                            ->default('0')
                            ->required(),
                        DatePicker::make('closed_from')
                            ->label('تعطیلی موقت از تاریخ')
                            ->jalali()
                            ->displayFormat('Y/m/d'),
                        DatePicker::make('closed_until')
                            ->label('تعطیلی موقت تا تاریخ')
                            ->jalali()
                            ->displayFormat('Y/m/d'),
                        Textarea::make('admin_note')
                            ->label('یادداشت برای دستیار هوشمند')
                            ->helperText('این متن مستقیماً در پرامپت هوش مصنوعی قرار می‌گیرد؛ مثلاً «این هفته تخفیف ویژه» یا «به دلیل برف فعلاً خطرناک است». فقط کسانی که به «تنظیمات دستیار سفر» دسترسی دارند می‌توانند آن را ویرایش کنند.')
                            ->disabled(fn () => ! auth()->user()?->can('trip-planner-settings.access'))
                            ->dehydrated(fn () => (bool) auth()->user()?->can('trip-planner-settings.access'))
                            ->columnSpanFull(),
                        Toggle::make('is_active')
                            ->label('فعال (در فهرست و پیشنهادها نمایش داده شود)')
                            ->helperText('جاذبه‌ی پیشنهادی هوش مصنوعی تا وقتی بازبینی و تأیید نشده فعال نمی‌شود.')
                            ->default(true)
                            ->disabled(fn (?Attraction $record) => $record?->source === 'ai_suggested' && $record->verified_at === null)
                            ->live(),
                        TextInput::make('sort_order')
                            ->label('ترتیب نمایش')
                            ->numeric()
                            ->default(0),
                    ]),
            ]);
    }
}
