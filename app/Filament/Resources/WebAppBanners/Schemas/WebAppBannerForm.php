<?php

namespace App\Filament\Resources\WebAppBanners\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class WebAppBannerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // ->disk('public'), unlike Documents' upload: this image is
                // shown directly on the public customer app page (no auth,
                // no download controller in front of it), so it has to live
                // somewhere a plain <img src> can reach - see
                // WebAppBanner::imageUrl().
                FileUpload::make('image_path')
                    ->label('تصویر بنر')
                    ->image()
                    ->disk('public')
                    ->directory('web-app/banners')
                    ->imageEditor()
                    ->required()
                    ->helperText('نسبت پیشنهادی تصویر: عریض و کوتاه (مثلاً ۱۶:۷) تا در بالای صفحه‌ی اپ زیبا نمایش داده شود.')
                    ->columnSpanFull(),
                TextInput::make('title')
                    ->label('عنوان بنر')
                    ->maxLength(255),
                TextInput::make('subtitle')
                    ->label('زیرعنوان')
                    ->maxLength(255),
                TextInput::make('link_url')
                    ->label('لینک (اختیاری)')
                    ->helperText('اگر مشتری روی بنر بزند، به این آدرس منتقل می‌شود.')
                    ->url()
                    ->maxLength(255),
                TextInput::make('sort_order')
                    ->label('ترتیب نمایش')
                    ->numeric()
                    ->default(0)
                    ->required(),
                Toggle::make('is_active')
                    ->label('نمایش در اپ')
                    ->default(true),
            ]);
    }
}
