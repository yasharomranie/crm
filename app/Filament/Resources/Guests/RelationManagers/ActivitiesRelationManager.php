<?php

namespace App\Filament\Resources\Guests\RelationManagers;

use App\Enums\CrmActivityType;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Read-only timeline of everything spatie/laravel-activitylog has recorded
 * for this guest - both automatic field-change entries (Guest::LogsActivity)
 * and manual interactions logged via LogCrmActivityAction. Logging a new
 * entry happens from the "ثبت تعامل جدید" button on the guest's own page,
 * not from here, since activity_log rows are an audit trail, not an
 * editable/deletable resource.
 */
class ActivitiesRelationManager extends RelationManager
{
    protected static string $relationship = 'activitiesAsSubject';

    protected static ?string $title = 'تاریخچه‌ی تعاملات';

    protected static ?string $modelLabel = 'تعامل';

    protected static ?string $pluralModelLabel = 'تعاملات';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('description')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('event')
                    ->label('نوع')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => CrmActivityType::tryFrom($state ?? '')?->getLabel() ?? match ($state) {
                        'created' => 'ایجاد پرونده',
                        'updated' => 'ویرایش اطلاعات',
                        'lead_captured' => 'ثبت از فرم سرنخ',
                        'trip_plan_created' => 'برنامه‌ی سفر',
                        'merged' => 'ادغام پرونده',
                        'email_sent' => 'ایمیل',
                        'sms_sent' => 'پیامک',
                        default => $state ?? '—',
                    })
                    ->color(fn (?string $state) => CrmActivityType::tryFrom($state ?? '')?->getColor() ?? 'gray'),
                TextColumn::make('description')
                    ->label('شرح')
                    ->wrap(),
                TextColumn::make('causer.name')
                    ->label('ثبت‌کننده')
                    ->placeholder('سیستم'),
                TextColumn::make('created_at')
                    ->label('زمان')
                    ->jalaliDateTime('Y/m/d H:i'),
            ])
            ->headerActions([
                //
            ])
            ->recordActions([
                //
            ])
            ->toolbarActions([
                //
            ]);
    }
}
