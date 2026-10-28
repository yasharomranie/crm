<?php

namespace App\Filament\Resources\GuideObjects;

use App\Enums\GuideObjectCategory;
use App\Enums\GuideObjectStatus;
use App\Filament\Resources\GuideObjects\Pages\CreateGuideObject;
use App\Filament\Resources\GuideObjects\Pages\EditGuideObject;
use App\Filament\Resources\GuideObjects\Pages\ListGuideObjects;
use App\Filament\Resources\GuideObjects\RelationManagers\ReferenceImagesRelationManager;
use App\Filament\Resources\GuideObjects\RelationManagers\VideosRelationManager;
use App\Models\GuideObject;
use App\Models\GuideSite;
use App\Models\GuideZone;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Number;

/** The things visitors can point a phone at: details, reference photos and the story video of each. */
class GuideObjectResource extends Resource
{
    protected static ?string $model = GuideObject::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCamera;

    protected static string|\UnitEnum|null $navigationGroup = 'راهنمای هوشمند گردشگری';

    protected static ?string $navigationLabel = 'اشیای قابل شناسایی';

    protected static ?string $modelLabel = 'شیء';

    protected static ?string $pluralModelLabel = 'اشیای قابل شناسایی';

    protected static ?string $recordTitleAttribute = 'title_fa';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('guide_site_id')
                    ->label('مجموعهٔ گردشگری')
                    ->relationship('site', 'name')
                    ->default(fn () => GuideSite::query()->value('id'))
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn ($set) => $set('guide_zone_id', null)),
                Select::make('guide_zone_id')
                    ->label('فضا')
                    ->options(fn (Get $get): array => GuideZone::query()->where('guide_site_id', $get('guide_site_id'))->orderBy('sort_order')->pluck('name', 'id')->all())
                    ->searchable(),
                TextInput::make('title_fa')->label('عنوان فارسی')->required()->maxLength(150),
                TextInput::make('title_en')->label('عنوان انگلیسی (اختیاری)')->maxLength(150),
                Select::make('category')->label('دسته‌بندی')->options(GuideObjectCategory::class)->default(GuideObjectCategory::Other)->required(),
                TextInput::make('location_note')->label('محل قرارگیری')->maxLength(255),
                Textarea::make('description')->label('توضیح کوتاه (نمایش به گردشگر)')->rows(4)->columnSpanFull(),
                FileUpload::make('cover_image')
                    ->label('تصویر شاخص')
                    ->image()
                    ->disk('public')
                    ->directory('guide/covers')
                    ->visibility('public')
                    ->maxSize(4096)
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp']),
                Toggle::make('is_active')
                    ->label('فعال')
                    ->helperText('خاموش‌کردن، شیء را بدون پاک‌شدن عکس‌ها و ویدیو از راهنما برمی‌دارد.')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['zone', 'site'])->withCount([
                'referenceImages',
                'referenceImages as processed_images_count' => fn (Builder $images) => $images->where('status', 'processed'),
                'videos as published_videos_count' => fn (Builder $videos) => $videos->where('is_published', true),
            ]))
            ->columns([
                ImageColumn::make('cover_image')->label('')->disk('public')->square()->size(48),
                TextColumn::make('title_fa')->label('عنوان')->searchable()->description(fn (GuideObject $record) => $record->title_en),
                TextColumn::make('category')->label('دسته')->badge()->color('gray'),
                TextColumn::make('zone.name')->label('فضا')->placeholder('—'),
                TextColumn::make('status')->label('وضعیت')->badge(),
                TextColumn::make('processed_images_count')
                    ->label('تصویر مرجع')
                    ->formatStateUsing(fn ($state, GuideObject $record) => Number::toPersian((int) $state).' از '.Number::toPersian((int) $record->reference_images_count)),
                TextColumn::make('published_videos_count')->label('ویدیو')->formatStateUsing(fn ($state) => Number::toPersian((int) $state)),
                TextColumn::make('updated_at')->label('آخرین تغییر')->jalaliDate('Y/m/d')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                SelectFilter::make('status')->label('وضعیت')->options(GuideObjectStatus::class),
                SelectFilter::make('category')->label('دسته')->options(GuideObjectCategory::class),
                SelectFilter::make('guide_site_id')->label('مجموعه')->relationship('site', 'name'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            ReferenceImagesRelationManager::class,
            VideosRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGuideObjects::route('/'),
            'create' => CreateGuideObject::route('/create'),
            'edit' => EditGuideObject::route('/{record}/edit'),
        ];
    }
}
