<?php

namespace Tests\Feature;

use App\Console\Commands\SyncAccessControlPermissions;
use App\Filament\Pages\Concerns\HasAccessControlledPage;
use Filament\Pages\Dashboard;
use Filament\Pages\Page;
use Filament\Resources\Resource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionClass;
use Tests\TestCase;

/**
 * The guard behind the role screen: every admin-panel resource and page must
 * be a module whose access can be given or withheld per role. A new screen
 * that nobody registers in config/access-control.php would otherwise be open
 * to every signed-in user and never show up under «نقش‌ها و سطوح دسترسی».
 */
class AccessControlCoverageTest extends TestCase
{
    use RefreshDatabase;

    /** Pages that are deliberately open to every signed-in user. */
    private const OPEN_PAGES = [
        'App\\Filament\\Pages\\Dashboard',
    ];

    /**
     * @return list<class-string>
     */
    private function classesIn(string $directory, string $namespace): array
    {
        $classes = [];

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(app_path($directory), \FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relative = str_replace(['/', '.php'], ['\\', ''], substr($file->getPathname(), strlen(app_path($directory)) + 1));
            $class = $namespace.'\\'.$relative;

            if (class_exists($class)) {
                $classes[] = $class;
            }
        }

        return $classes;
    }

    public function test_every_resource_model_is_a_registered_access_module(): void
    {
        $registered = collect(config('access-control.resources'))->pluck('model')->all();
        $missing = [];

        foreach ($this->classesIn('Filament/Resources', 'App\\Filament\\Resources') as $class) {
            $reflection = new ReflectionClass($class);

            if ($reflection->isAbstract() || ! $reflection->isSubclassOf(Resource::class)) {
                continue;
            }

            if (! in_array($class::getModel(), $registered, true)) {
                $missing[] = $class;
            }
        }

        $this->assertSame([], $missing, 'These resources are not in config/access-control.php "resources", so no role can be limited on them: '.implode(', ', $missing));
    }

    public function test_every_page_is_gated_and_registered_unless_it_is_deliberately_open(): void
    {
        $registered = collect(config('access-control.pages'))->pluck('page')->all();
        $ungated = [];
        $unregistered = [];

        foreach ($this->classesIn('Filament/Pages', 'App\\Filament\\Pages') as $class) {
            $reflection = new ReflectionClass($class);

            if ($reflection->isAbstract() || ! $reflection->isSubclassOf(Page::class) || in_array($class, self::OPEN_PAGES, true) || $reflection->isSubclassOf(Dashboard::class)) {
                continue;
            }

            if (! in_array(HasAccessControlledPage::class, class_uses_recursive($class), true)) {
                $ungated[] = $class;
            }

            if (! in_array($class, $registered, true)) {
                $unregistered[] = $class;
            }
        }

        $this->assertSame([], $ungated, 'These pages do not check any permission: '.implode(', ', $ungated));
        $this->assertSame([], $unregistered, 'These pages are not in config/access-control.php "pages": '.implode(', ', $unregistered));
    }

    public function test_every_page_checks_a_permission_that_the_sync_command_creates(): void
    {
        $defined = SyncAccessControlPermissions::allPermissionNames();
        $wrong = [];

        foreach (config('access-control.pages') as $module => $definition) {
            $permission = $definition['page']::accessControlPermission();

            if (! in_array($permission, $defined, true)) {
                $wrong[] = "{$module}: {$permission}";
            }
        }

        $this->assertSame([], $wrong, 'These pages check a permission that is never created: '.implode('; ', $wrong));
    }

    public function test_every_module_has_a_label_and_a_group_for_the_role_screen(): void
    {
        $incomplete = [];

        foreach (['resources', 'pages'] as $kind) {
            foreach (config("access-control.{$kind}") as $module => $definition) {
                if (blank($definition['label'] ?? null) || blank($definition['group'] ?? null)) {
                    $incomplete[] = $module;
                }
            }
        }

        $this->assertSame([], $incomplete);
    }

    public function test_the_two_web_app_content_modules_can_now_be_limited_per_role(): void
    {
        $this->artisan('access-control:sync');

        foreach (['web-app-banners', 'web-app-sections'] as $module) {
            $this->assertArrayHasKey($module, SyncAccessControlPermissions::groupedDefinitions()['وب‌اپ مشتریان']);
            $this->assertContains("{$module}.view_any", SyncAccessControlPermissions::allPermissionNames());
        }
    }
}
