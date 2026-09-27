<?php

namespace App\Filament\Resources\ExternalApiClients\Schemas;

use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ExternalApiClientForm
{
    /**
     * The full set of operation strings any external-API endpoint checks
     * via ExternalApiClient::hasPermission() - kept in sync by hand with
     * routes/api.php; a permission this list is missing simply can't be
     * granted to a client from the panel.
     */
    public static function operationOptions(): array
    {
        return [
            'product.read' => 'مشاهده‌ی محصولات (گیشه بلیط)',
            'ticket_variant.read' => 'مشاهده‌ی انواع بلیت',
            'ticket.issue' => 'صدور بلیط',
            'ticket.read' => 'مشاهده‌ی بلیط',
            'ticket.reprint' => 'چاپ مجدد بلیط',
            'ticket.refund' => 'بازپرداخت بلیط',
            'discount_code.validate' => 'اعتبارسنجی کد تخفیف',
            'gate.scan' => 'اسکن گیت',
            'room.read' => 'مشاهده‌ی موجودی اتاق',
            'booking.create' => 'ثبت رزرو',
            'booking.read' => 'مشاهده‌ی رزرو',
            'deal.create' => 'ثبت سرنخ (CRM)',
            'deal.read' => 'مشاهده‌ی سرنخ (CRM)',
            'form_submission.create' => 'ثبت پاسخ فرم',
            'document.upload' => 'بارگذاری سند',
            'document.read' => 'دانلود سند',
            'webhook.manage' => 'مدیریت وب‌هوک‌ها',
        ];
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('client_name')
                    ->label('نام اکانت / سامانه')
                    ->required()
                    ->maxLength(255),
                TextInput::make('company')
                    ->label('شرکت/پیمانکار (اختیاری)')
                    ->maxLength(255),
                TextInput::make('terminal_id')
                    ->label('شناسه‌ی ترمینال (اختیاری)')
                    ->maxLength(255),
                Select::make('created_by')
                    ->label('ثبت به نام کاربر')
                    ->relationship('creator', 'name')
                    ->searchable()
                    ->preload()
                    ->helperText('برای رکوردهایی که این اکانت از طریق API می‌سازد و به یک کاربر واقعی نیاز دارند (مثل ثبت فرم)، این کاربر به‌عنوان مسئول ثبت می‌شود.'),
                TextInput::make('rate_limit_per_minute')
                    ->label('سقف درخواست در دقیقه')
                    ->numeric()
                    ->default(60)
                    ->required(),
                CheckboxList::make('allowed_operations')
                    ->label('عملیات‌های مجاز')
                    ->options(self::operationOptions())
                    ->columns(2)
                    ->columnSpanFull(),
                Toggle::make('is_active')
                    ->label('فعال')
                    ->default(true),
            ]);
    }
}
