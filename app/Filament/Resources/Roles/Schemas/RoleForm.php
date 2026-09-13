<?php

namespace App\Filament\Resources\Roles\Schemas;

use App\Console\Commands\SyncAccessControlPermissions;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Models\Permission;

class RoleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('نام نقش (سطح دسترسی)')
                    ->helperText('مثلاً: مدیر هتل، حسابدار، پذیرش، سرپرست انبار')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),

                ...collect(SyncAccessControlPermissions::groupedDefinitions())
                    ->map(fn (array $modules, string $group) => static::groupSection($group, $modules))
                    ->values()
                    ->all(),
            ]);
    }

    /**
     * One collapsible section per navigation group, containing one
     * permission checkbox-list per module in that group.
     *
     * @param  array<string, array{label: string, permissions: array<string, string>}>  $modules
     */
    protected static function groupSection(string $group, array $modules): Section
    {
        return Section::make($group)
            ->collapsible()
            ->collapsed()
            ->columns(2)
            ->components(
                collect($modules)
                    ->map(fn (array $module, string $slug) => static::moduleCheckboxList($slug, $module))
                    ->values()
                    ->all(),
            );
    }

    /**
     * @param  array{label: string, permissions: array<string, string>}  $module
     */
    protected static function moduleCheckboxList(string $slug, array $module): CheckboxList
    {
        return CheckboxList::make("permissions_{$slug}")
            ->label($module['label'])
            ->relationship(
                name: 'permissions',
                titleAttribute: 'name',
                modifyQueryUsing: fn (Builder $query) => $query->where('name', 'like', "{$slug}.%"),
            )
            ->getOptionLabelFromRecordUsing(
                fn (Permission $record) => $module['permissions'][$record->name] ?? $record->name,
            )
            ->bulkToggleable()
            ->columns(2);
    }
}
