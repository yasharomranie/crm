<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;

class SyncAccessControlPermissions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'access-control:sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'همگام‌سازی مجوزهای سیستم با ماژول‌های تعریف‌شده در config/access-control.php';

    /**
     * Standard actions generated for every resource-backed module.
     *
     * @var array<string, string>
     */
    public const RESOURCE_ACTIONS = [
        'view_any' => 'مشاهدهٔ فهرست',
        'view' => 'مشاهدهٔ جزئیات',
        'create' => 'ایجاد',
        'update' => 'ویرایش',
        'delete' => 'حذف',
        'delete_any' => 'حذف گروهی',
    ];

    /**
     * The single action generated for every page-backed module.
     *
     * @var array<string, string>
     */
    public const PAGE_ACTIONS = [
        'access' => 'دسترسی',
    ];

    public function handle(): int
    {
        $names = static::allPermissionNames();

        foreach ($names as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $stale = Permission::whereNotIn('name', $names)->get();

        foreach ($stale as $permission) {
            $permission->delete();
        }

        $this->info(sprintf(
            '%d مجوز همگام‌سازی شد (%d مجوز قدیمی که دیگر متعلق به هیچ ماژولی نبود حذف شد).',
            count($names),
            $stale->count(),
        ));

        return self::SUCCESS;
    }

    /**
     * Every permission name the system currently defines, e.g. "documents.view_any".
     *
     * @return list<string>
     */
    public static function allPermissionNames(): array
    {
        $names = [];

        foreach (array_keys(config('access-control.resources', [])) as $module) {
            foreach (array_keys(self::RESOURCE_ACTIONS) as $action) {
                $names[] = "{$module}.{$action}";
            }
        }

        foreach (array_keys(config('access-control.pages', [])) as $module) {
            foreach (array_keys(self::PAGE_ACTIONS) as $action) {
                $names[] = "{$module}.{$action}";
            }
        }

        return $names;
    }

    /**
     * All module/permission definitions, grouped for a role-assignment form:
     *
     * ```
     * [
     *     'زیرساخت سازمانی' => [
     *         'departments' => [
     *             'label' => 'دپارتمان‌ها',
     *             'permissions' => ['departments.view_any' => 'مشاهدهٔ فهرست', ...],
     *         ],
     *         ...
     *     ],
     *     ...
     * ]
     * ```
     *
     * @return array<string, array<string, array{label: string, permissions: array<string, string>}>>
     */
    public static function groupedDefinitions(): array
    {
        $grouped = [];

        foreach (config('access-control.resources', []) as $module => $definition) {
            $grouped[$definition['group']][$module] = [
                'label' => $definition['label'],
                'permissions' => collect(self::RESOURCE_ACTIONS)
                    ->mapWithKeys(fn (string $label, string $action) => ["{$module}.{$action}" => $label])
                    ->all(),
            ];
        }

        foreach (config('access-control.pages', []) as $module => $definition) {
            $grouped[$definition['group']][$module] = [
                'label' => $definition['label'],
                'permissions' => collect(self::PAGE_ACTIONS)
                    ->mapWithKeys(fn (string $label, string $action) => ["{$module}.{$action}" => $label])
                    ->all(),
            ];
        }

        return $grouped;
    }
}
