<?php

namespace App\Filament\Resources\Contracts\Schemas;

use App\Filament\Support\FieldHelp;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ContractForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->label('عنوان')
                    ->required()
                    ->maxLength(255)
                    ->hintAction(FieldHelp::make('contracts.title')),
                Select::make('contract_category_id')
                    ->label('دسته')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload()
                    ->hintAction(FieldHelp::make('contracts.category')),
                Select::make('company_id')
                    ->label('کارفرما (شرکت ما)')
                    ->relationship('company', 'name')
                    ->searchable()
                    ->preload()
                    ->hintAction(FieldHelp::make('contracts.company')),
                TextInput::make('counterparty_name')
                    ->label('طرف مقابل')
                    ->required()
                    ->maxLength(255)
                    ->hintAction(FieldHelp::make('contracts.counterparty_name')),
                Textarea::make('body')
                    ->label('متن قرارداد')
                    ->required()
                    ->rows(20)
                    ->columnSpanFull()
                    ->hintAction(FieldHelp::make('contracts.body')),
            ]);
    }
}
