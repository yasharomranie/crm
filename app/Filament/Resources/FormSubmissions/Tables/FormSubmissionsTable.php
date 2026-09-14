<?php

namespace App\Filament\Resources\FormSubmissions\Tables;

use App\Enums\FormSubmissionStatus;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class FormSubmissionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reference_code')->label('شماره پیگیری')->fontFamily('mono')->searchable(),
                TextColumn::make('formTemplate.title')->label('نوع فرم')->searchable(),
                TextColumn::make('status')->label('وضعیت')->badge(),
                TextColumn::make('department.name')->label('دپارتمان')->placeholder('—'),
                TextColumn::make('submitter.name')->label('ثبت‌کننده'),
                TextColumn::make('workflowInstance.currentStep.name')->label('در انتظار')->placeholder('—'),
                TextColumn::make('submitted_at')->label('تاریخ ثبت')->jalaliDateTime('Y/m/d H:i')->sortable(),
            ])
            ->defaultSort('submitted_at', 'desc')
            ->filters([
                SelectFilter::make('status')->label('وضعیت')->options(FormSubmissionStatus::class),
            ])
            ->recordActions([
                ViewAction::make()->label('بررسی'),
            ]);
    }
}
