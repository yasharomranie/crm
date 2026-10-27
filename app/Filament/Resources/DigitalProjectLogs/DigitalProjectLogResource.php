<?php

namespace App\Filament\Resources\DigitalProjectLogs;

use App\Enums\DigitalProjectLogKind;
use App\Enums\DigitalProjectWorkCategory;
use App\Filament\Resources\DigitalProjectLogs\Pages\ManageDigitalProjectLogs;
use App\Models\DigitalProjectLog;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Morilog\Jalali\Jalalian;

/** Every journal line of every digital project in one place, newest day first. */
class DigitalProjectLogResource extends Resource
{
    protected static ?string $model = DigitalProjectLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPencilSquare;

    protected static string|\UnitEnum|null $navigationGroup = 'پروژه‌های دیجیتال';

    protected static ?string $navigationLabel = 'گزارش روزانه';

    protected static ?string $modelLabel = 'گزارش روزانه';

    protected static ?string $pluralModelLabel = 'گزارش‌های روزانه';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('digital_project_id')->label('پروژه')->relationship('project', 'name')->searchable()->preload()->required(),
                DatePicker::make('logged_on')->jalali()->displayFormat('Y/m/d')->label('تاریخ')->default(today())->required(),
                Select::make('kind')->label('نوع')->options(DigitalProjectLogKind::class)->default(DigitalProjectLogKind::Done)->required(),
                Select::make('work_category')->label('نوع کار (برای گزارش مدیریتی)')->options(DigitalProjectWorkCategory::class)->placeholder('—')->helperText('اگر انتخاب کنید، این کار در ستون همان دسته از گزارش هفتگی شمرده می‌شود.'),
                Textarea::make('body')->label('چه کاری کردید؟')->rows(4)->required()->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['project', 'author']))
            ->columns([
                TextColumn::make('project.name')->label('پروژه')->searchable(),
                TextColumn::make('kind')->label('نوع')->badge(),
                TextColumn::make('body')->label('گزارش')->searchable()->wrap(),
                TextColumn::make('author.name')->label('ثبت‌کننده')->placeholder('—'),
            ])
            ->defaultGroup(
                Group::make('logged_on')
                    ->label('روز')
                    ->getTitleFromRecordUsing(fn (DigitalProjectLog $record) => Jalalian::fromCarbon($record->logged_on)->format('Y/m/d'))
                    ->collapsible(),
            )
            ->groupingSettingsHidden()
            ->defaultSort('logged_on', 'desc')
            ->filters([
                SelectFilter::make('digital_project_id')->label('پروژه')->relationship('project', 'name'),
                SelectFilter::make('kind')->label('نوع')->options(DigitalProjectLogKind::class),
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

    public static function getPages(): array
    {
        return [
            'index' => ManageDigitalProjectLogs::route('/'),
        ];
    }
}
