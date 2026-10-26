<?php

namespace App\Filament\Resources\DigitalProjects\RelationManagers;

use App\Enums\DigitalProjectMetric;
use App\Models\DigitalProjectStat;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Number;

/** Numbers read off the channel by hand (followers, views...): one row per reading, so the table shows the trend. */
class StatsRelationManager extends RelationManager
{
    protected static string $relationship = 'stats';

    protected static ?string $title = 'آمار کانال';

    protected static ?string $modelLabel = 'آمار';

    protected static ?string $pluralModelLabel = 'آمارها';

    /** The project page is a view page, where relation managers are read-only unless they say otherwise. */
    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('metric')->label('کدام عدد؟')->options(DigitalProjectMetric::class)->default(DigitalProjectMetric::Followers)->required(),
                TextInput::make('value')->label('مقدار')->numeric()->integer()->minValue(0)->required(),
                DatePicker::make('recorded_on')->jalali()->displayFormat('Y/m/d')->label('تاریخ خواندن عدد')->default(today())->required(),
                TextInput::make('note')->label('توضیح (اختیاری)')->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('value')
            ->columns([
                TextColumn::make('recorded_on')->label('تاریخ')->jalaliDate('Y/m/d'),
                TextColumn::make('metric')->label('عدد')->badge(),
                TextColumn::make('value')->label('مقدار')->formatStateUsing(fn ($state) => Number::toPersian(number_format((int) $state))),
                TextColumn::make('change')
                    ->label('تغییر')
                    ->state(function (DigitalProjectStat $record): ?int {
                        $before = DigitalProjectStat::query()
                            ->where('digital_project_id', $record->digital_project_id)
                            ->where('metric', $record->metric->value)
                            ->where(fn ($earlier) => $earlier
                                ->whereDate('recorded_on', '<', $record->recorded_on)
                                ->orWhere(fn ($sameDay) => $sameDay->whereDate('recorded_on', $record->recorded_on)->where('id', '<', $record->id)))
                            ->orderByDesc('recorded_on')
                            ->orderByDesc('id')
                            ->first();

                        return $before ? $record->value - $before->value : null;
                    })
                    ->formatStateUsing(fn (?int $state) => $state === null ? '—' : ($state > 0 ? '+' : ($state < 0 ? '−' : '')).Number::toPersian(number_format(abs($state))))
                    ->color(fn (?int $state) => $state === null || $state === 0 ? 'gray' : ($state > 0 ? 'success' : 'danger'))
                    ->placeholder('—'),
                TextColumn::make('note')->label('توضیح')->placeholder('—')->wrap(),
            ])
            ->defaultSort('recorded_on', 'desc')
            ->headerActions([
                CreateAction::make()->mutateDataUsing(fn (array $data): array => $data + ['user_id' => auth()->id()]),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
