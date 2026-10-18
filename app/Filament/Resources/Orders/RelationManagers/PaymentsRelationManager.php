<?php

namespace App\Filament\Resources\Orders\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    protected static ?string $title = 'پرداخت‌ها';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('method')->label('روش')->badge(),
                TextColumn::make('amount')->label('مبلغ')->numeric()->suffix(' تومان'),
                TextColumn::make('reference')->label('مرجع')->placeholder('—'),
                TextColumn::make('paid_at')->label('زمان')->jalaliDateTime('Y/m/d H:i')->placeholder('—'),
            ]);
    }
}
