<?php

namespace App\Filament\Resources\GuestSegments;

use App\Domain\Crm\GuestSegmentQuery;
use App\Filament\Resources\Guests\Pages\ListGuests;
use App\Filament\Resources\GuestSegments\Pages\ManageGuestSegments;
use App\Models\GuestSegment;
use App\Models\Tag;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Number;

class GuestSegmentResource extends Resource
{
    protected static ?string $model = GuestSegment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFunnel;

    protected static string|\UnitEnum|null $navigationGroup = 'CRM و مشتریان';

    protected static ?string $modelLabel = 'بخش مشتریان';

    protected static ?string $pluralModelLabel = 'بخش‌بندی مشتریان';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('نام بخش')
                    ->helperText('مثلاً «مشتریان قدیمی که مدتی نیامده‌اند».')
                    ->required()
                    ->maxLength(255),
                Textarea::make('description')
                    ->label('توضیح (اختیاری)')
                    ->rows(2)
                    ->maxLength(1000),
                Select::make('match_type')
                    ->label('شرط‌ها چگونه ترکیب شوند؟')
                    ->options([
                        'all' => 'همهٔ شرط‌ها برقرار باشد',
                        'any' => 'حداقل یکی از شرط‌ها برقرار باشد',
                    ])
                    ->default('all')
                    ->required()
                    ->native(false),
                Repeater::make('conditions')
                    ->label('شرط‌ها')
                    ->helperText('این بخش ثابت نیست: هر بار که باز می‌کنید، مشتریان بر اساس همین شرط‌ها دوباره محاسبه می‌شوند.')
                    ->minItems(1)
                    ->required()
                    ->addActionLabel('افزودن شرط')
                    ->columnSpanFull()
                    ->schema([
                        Select::make('type')
                            ->label('شرط')
                            ->options(GuestSegmentQuery::typeLabels())
                            ->required()
                            ->live()
                            ->native(false),
                        Select::make('operator')
                            ->label('مقایسه')
                            ->options(['gte' => 'حداقل', 'lte' => 'حداکثر', 'eq' => 'دقیقاً'])
                            ->default('gte')
                            ->visible(fn (Get $get) => in_array($get('type'), GuestSegmentQuery::numericTypes(), true))
                            ->native(false),
                        TextInput::make('value')
                            ->label(fn (Get $get) => in_array($get('type'), GuestSegmentQuery::dayCountTypes(), true) ? 'تعداد روز' : 'مقدار')
                            ->numeric()
                            ->minValue(0)
                            ->required()
                            ->visible(fn (Get $get) => in_array($get('type'), [...GuestSegmentQuery::numericTypes(), ...GuestSegmentQuery::dayCountTypes()], true)),
                        Select::make('answer')
                            ->label('پاسخ')
                            ->options(['yes' => 'بله', 'no' => 'خیر'])
                            ->default('yes')
                            ->required()
                            ->visible(fn (Get $get) => in_array($get('type'), GuestSegmentQuery::yesNoTypes(), true))
                            ->native(false),
                        Select::make('tag_id')
                            ->label('برچسب')
                            ->options(fn () => Tag::query()->orderBy('name')->pluck('name', 'id'))
                            ->required()
                            ->searchable()
                            ->visible(fn (Get $get) => $get('type') === GuestSegmentQuery::TYPE_HAS_TAG),
                    ])
                    ->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('نام بخش')->searchable(),
                TextColumn::make('match_type')
                    ->label('ترکیب شرط‌ها')
                    ->formatStateUsing(fn (string $state) => $state === 'any' ? 'یکی از شرط‌ها' : 'همهٔ شرط‌ها')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('conditions')
                    ->label('تعداد شرط')
                    ->formatStateUsing(fn ($state, GuestSegment $record) => Number::toPersian(count($record->conditions ?? []))),
                TextColumn::make('members')
                    ->label('تعداد مشتری')
                    ->state(fn (GuestSegment $record) => Number::toPersian(app(GuestSegmentQuery::class)->count($record))),
            ])
            ->recordActions([
                Action::make('viewMembers')
                    ->label('مشاهدهٔ مشتریان')
                    ->icon('heroicon-o-users')
                    ->url(fn (GuestSegment $record) => ListGuests::getUrl(['filters' => ['segment' => ['value' => $record->id]]])),
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
            'index' => ManageGuestSegments::route('/'),
        ];
    }
}
