<?php

namespace App\Filament\Resources\RoomTypes;

use App\Filament\Resources\RoomTypes\Pages\ManageRoomTypes;
use App\Filament\Support\FieldHelp;
use App\Models\RoomType;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RoomTypeResource extends Resource
{
    protected static ?string $model = RoomType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHomeModern;

    protected static string|\UnitEnum|null $navigationGroup = 'اقامتی و هتلداری';

    protected static ?string $modelLabel = 'نوع اتاق/ویلا';

    protected static ?string $pluralModelLabel = 'انواع اتاق/ویلا';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('نام نوع اتاق')
                    ->helperText('مثلاً «ویلای دو نفره» یا «سوئیت خانوادگی»')
                    ->required()
                    ->maxLength(255)
                    ->hintAction(FieldHelp::make('room-types.name')),
                TextInput::make('capacity')
                    ->label('ظرفیت نفرات')
                    ->numeric()
                    ->default(2)
                    ->required()
                    ->hintAction(FieldHelp::make('room-types.capacity')),
                TextInput::make('base_price')
                    ->label('نرخ پایه هر شب (تومان)')
                    ->numeric()
                    ->required()
                    ->hintAction(FieldHelp::make('room-types.base_price')),
                TextInput::make('min_nights')
                    ->label('حداقل مدت اقامت (شب)')
                    ->numeric()->minValue(1)->maxValue(60)->default(1)->required()
                    ->helperText('رزرو کوتاه‌تر از این رد می‌شود. برای فصل‌های شلوغ می‌توانید در «قیمت‌گذاری پویا» حداقل شب بیشتری بگذارید.'),
                TextInput::make('check_in_time')
                    ->label('ساعت ورود')
                    ->default('14:00')
                    ->regex('/^([01]\d|2[0-3]):[0-5]\d$/')
                    ->validationMessages(['regex' => 'ساعت را به شکل ۱۴:۰۰ بنویسید (۲۴ ساعته).'])
                    ->required()
                    ->helperText('در پیامک رزرو با {check_in_time} می‌آید.'),
                TextInput::make('check_out_time')
                    ->label('ساعت خروج')
                    ->default('12:00')
                    ->regex('/^([01]\d|2[0-3]):[0-5]\d$/')
                    ->validationMessages(['regex' => 'ساعت را به شکل ۱۲:۰۰ بنویسید (۲۴ ساعته).'])
                    ->required()
                    ->helperText('در پیامک رزرو با {check_out_time} می‌آید.'),
                TextInput::make('base_occupancy')
                    ->label('بزرگسالِ شاملِ نرخ پایه')
                    ->numeric()->minValue(1)->maxValue(50)
                    ->helperText('خالی = هزینه‌ی نفر اضافه گرفته نمی‌شود. اگر پر شود، هر بزرگسال بیشتر از این عدد در هر شب «هزینه‌ی بزرگسال اضافه» می‌دهد.'),
                TextInput::make('extra_adult_fee')
                    ->label('هزینه‌ی هر بزرگسال اضافه (تومان در شب)')
                    ->numeric()->minValue(0)->default(0),
                TextInput::make('extra_child_fee')
                    ->label('هزینه‌ی هر کودک (تومان در شب)')
                    ->numeric()->minValue(0)->default(0)
                    ->helperText('صفر = کودک رایگان است.'),
                Textarea::make('description')
                    ->label('توضیحات')
                    ->columnSpanFull()
                    ->hintAction(FieldHelp::make('room-types.description')),
                Toggle::make('is_active')
                    ->label('فعال')
                    ->default(true)
                    ->hintAction(FieldHelp::make('room-types.is_active')),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('نام')->searchable(),
                TextColumn::make('capacity')->label('ظرفیت')->numeric(),
                TextColumn::make('base_price')->label('نرخ پایه هر شب')->numeric()->suffix(' تومان'),
                TextColumn::make('rooms_count')->label('تعداد واحد')->counts('rooms')->numeric(),
                IconColumn::make('is_active')->label('فعال')->boolean(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
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
            'index' => ManageRoomTypes::route('/'),
        ];
    }
}
