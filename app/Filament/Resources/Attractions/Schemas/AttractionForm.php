<?php

namespace App\Filament\Resources\Attractions\Schemas;

use App\Enums\AttractionCategory;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
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
                            ->helperText('این متن مستقیماً در پرامپت هوش مصنوعی قرار می‌گیرد؛ مثلاً «این هفته تخفیف ویژه» یا «به دلیل برف فعلاً خطرناک است».')
                            ->columnSpanFull(),
                        Toggle::make('is_active')
                            ->label('فعال (در فهرست و پیشنهادها نمایش داده شود)')
                            ->default(true)
                            ->live(),
                        TextInput::make('sort_order')
                            ->label('ترتیب نمایش')
                            ->numeric()
                            ->default(0),
                    ]),
            ]);
    }
}
