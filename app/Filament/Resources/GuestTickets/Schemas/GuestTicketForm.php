<?php

namespace App\Filament\Resources\GuestTickets\Schemas;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Filament\Support\FieldHelp;
use App\Models\Guest;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class GuestTicketForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('guest_id')
                    ->label('مشتری')
                    ->relationship('guest', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->createOptionForm([
                        TextInput::make('name')->label('نام و نام خانوادگی')->required(),
                        TextInput::make('mobile')->label('شماره موبایل')->tel(),
                    ])
                    ->createOptionUsing(fn (array $data) => Guest::create($data)->id)
                    ->hintAction(FieldHelp::make('guest-tickets.guest_id')),
                TextInput::make('subject')
                    ->label('موضوع')
                    ->required()
                    ->maxLength(255)
                    ->hintAction(FieldHelp::make('guest-tickets.subject')),
                Textarea::make('description')
                    ->label('شرح شکایت/درخواست')
                    ->columnSpanFull()
                    ->hintAction(FieldHelp::make('guest-tickets.description')),
                Select::make('priority')
                    ->label('فوریت')
                    ->options(TicketPriority::class)
                    ->default(TicketPriority::Normal)
                    ->required()
                    ->helperText('مهلت پاسخ‌گویی (SLA) بر اساس فوریت به‌صورت خودکار تنظیم می‌شود.')
                    ->hintAction(FieldHelp::make('guest-tickets.priority')),
                Select::make('status')
                    ->label('وضعیت')
                    ->options(TicketStatus::class)
                    ->default(TicketStatus::Open)
                    ->required()
                    ->hintAction(FieldHelp::make('guest-tickets.status')),
            ]);
    }
}
