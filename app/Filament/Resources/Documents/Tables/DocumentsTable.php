<?php

namespace App\Filament\Resources\Documents\Tables;

use App\Enums\DocumentAccessLevel;
use App\Models\Company;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Number;

class DocumentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->label('عنوان سند')->searchable(),
                TextColumn::make('folder.name')->label('پوشه')->placeholder('—'),
                TextColumn::make('access_level')->label('سطح دسترسی')->badge(),
                TextColumn::make('department.name')->label('دپارتمان مجاز')->placeholder('—'),
                TextColumn::make('department.company.name')->label('شرکت')->placeholder('—'),
                TextColumn::make('tags.name')->label('برچسب‌ها')->badge()->separator(','),
                TextColumn::make('size_kb')->label('حجم')->formatStateUsing(fn (?int $state) => $state ? Number::toPersian(number_format($state)).' کیلوبایت' : '—'),
                TextColumn::make('uploader.name')->label('بارگذاری‌کننده'),
                TextColumn::make('created_at')->label('تاریخ بارگذاری')->jalaliDate('Y/m/d'),
            ])
            ->filters([
                SelectFilter::make('access_level')
                    ->label('سطح دسترسی')
                    ->options(DocumentAccessLevel::class),
                SelectFilter::make('company')
                    ->label('شرکت')
                    ->options(fn () => Company::query()->orderBy('name')->pluck('name', 'id'))
                    ->query(fn (Builder $query, array $data) => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $query, $companyId) => $query->whereHas(
                            'department',
                            fn (Builder $query) => $query->where('company_id', $companyId),
                        ),
                    )),
            ])
            ->recordActions([
                Action::make('download')
                    ->label('دانلود')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->url(fn ($record) => route('documents.download', $record))
                    ->openUrlInNewTab(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
