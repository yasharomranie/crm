<?php

namespace App\Filament\Resources\Bookings\Schemas;

use App\Domain\Hospitality\StayPricing;
use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Exceptions\InvalidPromoCodeException;
use App\Filament\Support\FieldHelp;
use App\Models\Booking;
use App\Models\BookingPromoCode;
use App\Models\CancellationPolicy;
use App\Models\Guest;
use App\Models\RatePlan;
use App\Models\Room;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Carbon;
use Illuminate\Support\Number;

class BookingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('room_id')
                    ->label('اتاق/ویلا')
                    ->live(onBlur: true)
                    ->relationship('room', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->hintAction(FieldHelp::make('bookings.room_id')),
                Select::make('guest_id')
                    ->label('مهمان')
                    ->relationship('guest', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->createOptionForm([
                        TextInput::make('name')->label('نام و نام خانوادگی')->required(),
                        TextInput::make('mobile')->label('شماره موبایل')->tel(),
                    ])
                    ->createOptionUsing(fn (array $data) => Guest::create($data)->id)
                    ->hintAction(FieldHelp::make('bookings.guest_id')),
                DatePicker::make('check_in_date')
                    ->jalali()
                    ->displayFormat('Y/m/d')
                    ->label('تاریخ ورود')
                    ->live(onBlur: true)
                    ->required()
                    ->default(now())
                    ->hintAction(FieldHelp::make('bookings.check_in_date')),
                DatePicker::make('check_out_date')
                    ->jalali()
                    ->displayFormat('Y/m/d')
                    ->label('تاریخ خروج')
                    ->live(onBlur: true)
                    ->required()
                    ->default(now()->addDay())
                    ->afterOrEqual('check_in_date')
                    ->hintAction(FieldHelp::make('bookings.check_out_date')),
                TextInput::make('adults_count')
                    ->label('تعداد بزرگسال')
                    ->live(onBlur: true)
                    ->numeric()
                    ->default(1)
                    ->required()
                    ->hintAction(FieldHelp::make('bookings.adults_count')),
                TextInput::make('children_count')
                    ->label('تعداد کودک')
                    ->live(onBlur: true)
                    ->numeric()
                    ->default(0)
                    ->hintAction(FieldHelp::make('bookings.children_count')),
                Select::make('hold_hours')
                    ->label('نگه‌داشتن موقت')
                    ->options([6 => '۶ ساعت', 12 => '۱۲ ساعت', 24 => '۲۴ ساعت', 48 => '۴۸ ساعت', 72 => '۷۲ ساعت'])
                    ->placeholder('بدون مهلت')
                    ->helperText('فقط برای رزرو «در انتظار تأیید»: اگر تا پایان مهلت هیچ پرداختی نیامد، رزرو خودکار لغو (بدون جریمه) و اتاق آزاد می‌شود. با «لینک پرداخت» می‌توانید پرداخت و تأیید را به مهمان بسپارید.')
                    ->visible(fn (?Booking $record) => $record === null),
                TextInput::make('promo_code')
                    ->label('کد تخفیف')
                    ->maxLength(40)
                    ->live(onBlur: true)
                    ->dehydrated()
                    ->helperText('اگر مهمان کد تخفیف دارد اینجا بنویسید؛ اعتبار آن همین‌جا بررسی می‌شود.')
                    ->rule(fn (Get $get) => function (string $attribute, $value, \Closure $fail) use ($get) {
                        if (blank($value)) {
                            return;
                        }

                        try {
                            BookingPromoCode::resolve($value, self::nights($get));
                        } catch (InvalidPromoCodeException $exception) {
                            $fail($exception->getMessage());
                        }
                    })
                    ->visible(fn (?Booking $record) => $record === null),
                Placeholder::make('price_estimate')
                    ->label('برآورد قیمت')
                    ->content(fn (Get $get) => self::estimate($get))
                    ->columnSpanFull()
                    ->visible(fn (?Booking $record) => $record === null),
                Select::make('rate_plan_id')
                    ->label('نرخ فروش')
                    ->relationship('ratePlan', 'name', fn ($query) => $query->where('is_active', true)->orderBy('sort_order'))
                    ->searchable()
                    ->preload()
                    ->placeholder('نرخ معمول')
                    ->live()
                    ->helperText('مثلاً «با صبحانه» یا «غیرقابل بازگشت»؛ قیمت هر شب و (در صورت تعریف) سیاست لغو از آن می‌آید.')
                    ->visible(fn (?Booking $record) => $record === null),
                Select::make('status')
                    ->label('وضعیت رزرو')
                    ->options(BookingStatus::class)
                    ->default(BookingStatus::Pending)
                    ->required()
                    ->helperText('برای چک‌این/چک‌اوت سریع، از دکمه‌های اختصاصی روی جدول رزروها استفاده کنید.')
                    ->hintAction(FieldHelp::make('bookings.status')),
                Select::make('source')
                    ->label('منبع رزرو')
                    ->options(BookingSource::class)
                    ->default(BookingSource::WalkIn)
                    ->required()
                    ->hintAction(FieldHelp::make('bookings.source')),
                Select::make('sales_channel_id')
                    ->label('کانال فروش')
                    ->relationship('channel', 'name')
                    ->searchable()
                    ->preload()
                    ->helperText('در صورت ورود از طریق سایت واسط (OTA)، کانال را انتخاب کنید تا کمیسیون آن هنگام تسویه محاسبه شود.')
                    ->hintAction(FieldHelp::make('bookings.sales_channel_id')),
                Select::make('cancellation_policy_id')
                    ->label('سیاست لغو')
                    ->relationship('cancellationPolicy', 'name', fn ($query) => $query->where('is_active', true))
                    ->searchable()
                    ->preload()
                    ->placeholder('سیاست پیش‌فرض')
                    ->helperText('شرایط سیاست در لحظه‌ی ثبت رزرو روی همین رزرو ذخیره می‌شود؛ تغییر بعدیِ سیاست روی این رزرو اثری ندارد.')
                    ->visible(fn (?Booking $record) => $record === null),
                Placeholder::make('cancellation_terms_summary')
                    ->label('شرایط لغو این رزرو')
                    ->content(fn (?Booking $record) => $record?->cancellation_terms ? CancellationPolicy::describe($record->cancellation_terms) : 'بدون سیاست لغو (جریمه‌ای اعمال نمی‌شود)')
                    ->columnSpanFull()
                    ->visible(fn (?Booking $record) => $record !== null),
                Placeholder::make('precheckin_summary')
                    ->label('پیش‌ثبت‌نام آنلاین مهمان')
                    ->content(function (?Booking $record) {
                        $precheckin = $record?->precheckin;

                        if (! $precheckin?->isCompleted()) {
                            return 'هنوز انجام نشده';
                        }

                        return collect([
                            'کد ملی: '.$precheckin->national_code,
                            $precheckin->arrival_time ? 'ساعت رسیدن: '.$precheckin->arrival_time : null,
                            count($precheckin->companions ?? []) ? 'همراهان: '.collect($precheckin->companions)->map(fn (array $row) => $row['name'].($row['national_code'] ? " ({$row['national_code']})" : ''))->implode('، ') : null,
                            $precheckin->requests ? 'درخواست: '.$precheckin->requests : null,
                        ])->filter()->implode(' - ');
                    })
                    ->columnSpanFull()
                    ->visible(fn (?Booking $record) => $record !== null),
                Textarea::make('notes')
                    ->label('یادداشت')
                    ->columnSpanFull()
                    ->hintAction(FieldHelp::make('bookings.notes')),
                Section::make('خلاصه‌ی صورت‌حساب')
                    ->visible(fn (?Booking $record) => $record !== null)
                    ->columns(5)
                    ->columnSpanFull()
                    ->schema([
                        Placeholder::make('folio_total')
                            ->label('کل صورت‌حساب')
                            ->content(fn (?Booking $record) => self::money($record?->totalAmount() ?? 0)),
                        Placeholder::make('folio_paid')
                            ->label('پرداخت‌شده (کیف پول + دریافتی)')
                            ->content(fn (?Booking $record) => self::money($record?->paidAmount() ?? 0)),
                        Placeholder::make('folio_balance')
                            ->label('مانده')
                            ->content(fn (?Booking $record) => self::money($record?->balanceDue() ?? 0)),
                        Placeholder::make('folio_status')
                            ->label('وضعیت پرداخت')
                            ->content(fn (?Booking $record) => $record?->paymentStatus()->getLabel() ?? '—'),
                        Placeholder::make('damage_deposit')
                            ->label('ودیعه‌ی خسارت (جدا از صورت‌حساب)')
                            ->content(fn (?Booking $record) => $record?->damageDeposit?->held_amount
                                ? self::money($record->damageDeposit->held_amount).' - '.$record->damageDeposit->statusLabel()
                                : '—'),
                    ]),
            ]);
    }

    private static function nights(Get $get): int
    {
        try {
            return max(1, (int) Carbon::parse($get('check_in_date'))->startOfDay()->diffInDays(Carbon::parse($get('check_out_date'))->startOfDay()));
        } catch (\Throwable) {
            return 1;
        }
    }

    /**
     * What the stay would cost with the dates, guests and code typed so far.
     */
    private static function estimate(Get $get): string
    {
        $room = Room::query()->with('roomType.priceRules')->find($get('room_id'));

        if (! $room || blank($get('check_in_date')) || blank($get('check_out_date'))) {
            return 'اتاق و تاریخ‌ها را انتخاب کنید.';
        }

        try {
            $checkIn = Carbon::parse($get('check_in_date'));
            $checkOut = Carbon::parse($get('check_out_date'));
            $promo = filled($get('promo_code')) ? BookingPromoCode::resolve($get('promo_code'), self::nights($get)) : null;
            $ratePlan = filled($get('rate_plan_id')) ? RatePlan::query()->find($get('rate_plan_id')) : null;
            $quote = app(StayPricing::class)->quote($room->roomType, $checkIn, $checkOut, max(1, (int) $get('adults_count')), (int) $get('children_count'), $promo, $ratePlan);
        } catch (InvalidPromoCodeException $exception) {
            return $exception->getMessage();
        } catch (\Throwable) {
            return '—';
        }

        $parts = [self::money($quote['total']).' برای '.Number::toPersian($quote['nights']).' شب'];

        if ($quote['extra_guests_total'] > 0) {
            $parts[] = 'شامل '.self::money($quote['extra_guests_total']).' نفر اضافه';
        }

        if ($quote['stay_discount'] > 0) {
            $parts[] = 'تخفیف اقامت طولانی '.self::money($quote['stay_discount']);
        }

        if ($quote['promo_discount'] > 0) {
            $parts[] = 'کد تخفیف '.self::money($quote['promo_discount']);
        }

        if ($quote['nights'] < $quote['minimum_nights']) {
            $parts[] = 'هشدار: حداقل اقامت '.Number::toPersian($quote['minimum_nights']).' شب است';
        }

        return implode(' — ', $parts);
    }

    private static function money(int $amount): string
    {
        return Number::toPersian(number_format($amount)).' تومان';
    }
}
