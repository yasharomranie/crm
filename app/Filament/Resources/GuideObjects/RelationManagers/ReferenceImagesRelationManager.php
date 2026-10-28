<?php

namespace App\Filament\Resources\GuideObjects\RelationManagers;

use App\Enums\GuideReferenceStatus;
use App\Models\GuideObject;
use App\Models\GuideReferenceImage;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\FileUpload;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** The photos a phone learns an object from; 10 to 20 from different angles and distances is the starting advice. */
class ReferenceImagesRelationManager extends RelationManager
{
    protected static string $relationship = 'referenceImages';

    protected static ?string $title = 'تصاویر مرجع';

    protected static ?string $modelLabel = 'تصویر مرجع';

    protected static ?string $pluralModelLabel = 'تصاویر مرجع';

    /** The object page is an edit page, so this tab must say it is writable. */
    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('path')
            ->columns([
                ImageColumn::make('path')->label('تصویر')->disk('public')->square()->size(64),
                TextColumn::make('status')->label('وضعیت پردازش')->badge(),
                TextColumn::make('error')->label('خطا')->placeholder('—')->wrap(),
                TextColumn::make('processed_at')->label('زمان پردازش')->jalaliDate('Y/m/d H:i')->placeholder('—'),
            ])
            ->defaultSort('id', 'desc')
            ->headerActions([
                CreateAction::make()
                    ->label('افزودن تصاویر مرجع')
                    ->modalHeading('افزودن تصاویر مرجع')
                    ->schema([
                        FileUpload::make('paths')
                            ->label('تصاویر (از زوایا و فاصله‌های مختلف)')
                            ->helperText('تا ۲۰ تصویر JPG، PNG یا WebP، هرکدام حداکثر ۶ مگابایت.')
                            ->image()
                            ->multiple()
                            ->maxFiles(20)
                            ->maxSize(6144)
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->disk('public')
                            ->directory('guide/references')
                            ->visibility('public')
                            ->required(),
                    ])
                    ->using(function (array $data, RelationManager $livewire): Model {
                        /** @var GuideObject $object */
                        $object = $livewire->getOwnerRecord();
                        $object->addReferenceImages(array_values($data['paths']));

                        return $object->referenceImages()->latest('id')->firstOrFail();
                    }),
            ])
            ->recordActions([
                Action::make('reprocess')
                    ->label('پردازش مجدد')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->color('gray')
                    ->visible(fn (GuideReferenceImage $record) => $record->status !== GuideReferenceStatus::Pending)
                    ->action(fn (GuideReferenceImage $record) => $record->reprocess()),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ])
            ->emptyStateHeading('هنوز تصویر مرجعی نیست')
            ->emptyStateDescription('چند عکس از شیء بگیرید و اضافه کنید تا برای شناسایی آماده شود.');
    }
}
