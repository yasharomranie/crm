<?php

namespace App\Filament\Resources\AttendanceRecords\Tables;

use App\Enums\AttendanceStatus;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AttendanceRecordsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')->label('کاربر')->searchable(),
                TextColumn::make('user.department.name')->label('دپارتمان')->placeholder('—'),
                TextColumn::make('date')->label('تاریخ')->date('Y/m/d')->sortable(),
                TextColumn::make('check_in_at')->label('ورود')->time('H:i')->placeholder('—'),
                TextColumn::make('check_out_at')->label('خروج')->time('H:i')->placeholder('—'),
                TextColumn::make('status')->label('وضعیت')->badge(),
                TextColumn::make('late_minutes')->label('تأخیر')->suffix(' دقیقه')->toggleable(),
                TextColumn::make('early_leave_minutes')->label('تعجیل در خروج')->suffix(' دقیقه')->toggleable(),
                TextColumn::make('worked_minutes')->label('کارکرد')->formatStateUsing(function (int $state) {
                    $hours = intdiv($state, 60);
                    $minutes = $state % 60;

                    return "{$hours} ساعت {$minutes} دقیقه";
                }),
            ])
            ->defaultSort('date', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('وضعیت')
                    ->options(AttendanceStatus::class),
                SelectFilter::make('user_id')
                    ->label('کاربر')
                    ->relationship('user', 'name')
                    ->searchable(),
                Filter::make('date')
                    ->schema([
                        DatePicker::make('from')->label('از تاریخ'),
                        DatePicker::make('until')->label('تا تاریخ'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $query, $date) => $query->whereDate('date', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $query, $date) => $query->whereDate('date', '<=', $date));
                    }),
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
}
