<?php

namespace App\Filament\Resources\HandoverNotes;

use App\Filament\Resources\HandoverNotes\Pages\ManageHandoverNotes;
use App\Models\HandoverNote;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class HandoverNoteResource extends Resource
{
    protected static ?string $model = HandoverNote::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|\UnitEnum|null $navigationGroup = 'اقامتی و هتلداری';

    protected static ?string $modelLabel = 'یادداشت تحویل شیفت';

    protected static ?string $pluralModelLabel = 'دفتر تحویل شیفت';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Textarea::make('body')->label('یادداشت')->helperText('آنچه شیفت بعد باید بداند یا انجام دهد.')->required()->columnSpanFull(),
                Select::make('room_id')->label('اتاق (اختیاری)')->relationship('room', 'name')->searchable()->preload(),
                Select::make('booking_id')->label('رزرو (اختیاری)')->relationship('booking', 'reference_code')->searchable(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['room', 'booking', 'author']))
            ->columns([
                TextColumn::make('body')->label('یادداشت')->wrap()->searchable(),
                TextColumn::make('room.name')->label('اتاق')->placeholder('—'),
                TextColumn::make('booking.reference_code')->label('رزرو')->placeholder('—'),
                TextColumn::make('author.name')->label('ثبت‌کننده')->placeholder('—'),
                TextColumn::make('created_at')->label('زمان ثبت')->jalaliDateTime('Y/m/d H:i'),
                TextColumn::make('resolved_at')->label('انجام شد')->jalaliDateTime('Y/m/d H:i')->placeholder('باز'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Filter::make('open')->label('فقط باز')->default()
                    ->query(fn (Builder $query) => $query->whereNull('resolved_at')),
            ])
            ->recordActions([
                Action::make('resolve')
                    ->label('انجام شد')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->visible(fn (HandoverNote $record) => $record->isOpen())
                    ->action(fn (HandoverNote $record) => $record->resolve(auth()->id())),
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
            'index' => ManageHandoverNotes::route('/'),
        ];
    }
}
