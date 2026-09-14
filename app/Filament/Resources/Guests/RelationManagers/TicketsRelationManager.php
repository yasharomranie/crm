<?php

namespace App\Filament\Resources\Guests\RelationManagers;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TicketsRelationManager extends RelationManager
{
    protected static string $relationship = 'tickets';

    protected static ?string $title = 'تیکت‌های پشتیبانی';

    protected static ?string $modelLabel = 'تیکت';

    protected static ?string $pluralModelLabel = 'تیکت‌ها';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('subject')
                    ->label('موضوع')
                    ->required()
                    ->maxLength(255),
                Textarea::make('description')
                    ->label('شرح شکایت/درخواست')
                    ->columnSpanFull(),
                Select::make('priority')
                    ->label('فوریت')
                    ->options(TicketPriority::class)
                    ->default(TicketPriority::Normal)
                    ->required()
                    ->helperText('مهلت پاسخ‌گویی (SLA) بر اساس فوریت به‌صورت خودکار تنظیم می‌شود.'),
                Select::make('status')
                    ->label('وضعیت')
                    ->options(TicketStatus::class)
                    ->default(TicketStatus::Open)
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('subject')
            ->columns([
                TextColumn::make('subject')->label('موضوع'),
                TextColumn::make('priority')->label('فوریت')->badge(),
                TextColumn::make('status')->label('وضعیت')->badge(),
                TextColumn::make('sla_due_at')->label('مهلت پاسخ')->jalaliDateTime('Y/m/d H:i')->placeholder('—'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make(),
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
