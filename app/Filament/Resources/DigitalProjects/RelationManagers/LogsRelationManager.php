<?php

namespace App\Filament\Resources\DigitalProjects\RelationManagers;

use App\Enums\DigitalProjectLogKind;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LogsRelationManager extends RelationManager
{
    protected static string $relationship = 'logs';

    protected static ?string $title = 'گزارش روزانه';

    protected static ?string $modelLabel = 'گزارش';

    protected static ?string $pluralModelLabel = 'گزارش‌ها';

    /** The project page is a view page, where relation managers are read-only unless they say otherwise. */
    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                DatePicker::make('logged_on')->jalali()->displayFormat('Y/m/d')->label('تاریخ')->default(today())->required(),
                Select::make('kind')->label('نوع')->options(DigitalProjectLogKind::class)->default(DigitalProjectLogKind::Done)->required(),
                Textarea::make('body')->label('چه کاری کردید؟')->rows(4)->required()->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('body')
            ->columns([
                TextColumn::make('logged_on')->label('تاریخ')->jalaliDate('Y/m/d'),
                TextColumn::make('kind')->label('نوع')->badge(),
                TextColumn::make('body')->label('گزارش')->wrap(),
                TextColumn::make('author.name')->label('ثبت‌کننده')->placeholder('—'),
            ])
            ->defaultSort('logged_on', 'desc')
            ->headerActions([
                CreateAction::make()->mutateDataUsing(fn (array $data): array => $data + ['user_id' => auth()->id()]),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
