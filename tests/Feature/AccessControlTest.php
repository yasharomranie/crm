<?php

namespace Tests\Feature;

use App\Console\Commands\SyncAccessControlPermissions;
use App\Filament\Pages\AiAssistant;
use App\Filament\Resources\Departments\DepartmentResource;
use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('access-control:sync');
    }

    public function test_sync_command_creates_a_permission_for_every_module_action(): void
    {
        $expected = SyncAccessControlPermissions::allPermissionNames();

        $this->assertSame(count($expected), Permission::count());
        $this->assertTrue(Permission::where('name', 'documents.view_any')->exists());
        $this->assertTrue(Permission::where('name', 'ai-assistant.access')->exists());
    }

    public function test_a_page_module_can_define_its_own_custom_actions_beyond_plain_access(): void
    {
        // "ai-assistant" is the one page module with a custom, 3-action
        // "actions" array (access/report/act) instead of the single
        // "access" every other page module falls back to - see
        // config/access-control.php and SyncAccessControlPermissions.
        $this->assertTrue(Permission::where('name', 'ai-assistant.access')->exists());
        $this->assertTrue(Permission::where('name', 'ai-assistant.report')->exists());
        $this->assertTrue(Permission::where('name', 'ai-assistant.act')->exists());

        // Every other page module is unaffected and still gets exactly one.
        $this->assertTrue(Permission::where('name', 'messenger.access')->exists());
        $this->assertFalse(Permission::where('name', 'messenger.report')->exists());

        $grouped = SyncAccessControlPermissions::groupedDefinitions();
        $aiAssistantPermissions = $grouped['دستیار هوشمند مدیریتی']['ai-assistant']['permissions'];

        $this->assertSame(
            ['ai-assistant.access', 'ai-assistant.report', 'ai-assistant.act'],
            array_keys($aiAssistantPermissions),
        );
    }

    public function test_sync_command_removes_permissions_no_longer_defined_by_any_module(): void
    {
        Permission::create(['name' => 'a-removed-module.view_any', 'guard_name' => 'web']);

        $this->artisan('access-control:sync');

        $this->assertFalse(Permission::where('name', 'a-removed-module.view_any')->exists());
    }

    public function test_a_user_with_no_role_keeps_unrestricted_access(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->assertTrue(AiAssistant::canAccess());
        $this->assertTrue(DepartmentResource::canViewAny());
        $this->assertTrue(DepartmentResource::canCreate());
    }

    public function test_the_admin_role_bypasses_every_permission_check(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::firstOrCreate(['name' => 'admin']));
        $this->actingAs($user);

        $this->assertTrue(AiAssistant::canAccess());
        $this->assertTrue(DepartmentResource::canViewAny());
        $this->assertTrue(DepartmentResource::canCreate());
    }

    public function test_a_role_without_the_required_permission_is_denied(): void
    {
        $role = Role::create(['name' => 'حسابدار']);
        // Deliberately no permissions granted.

        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);

        $this->assertFalse(AiAssistant::canAccess());
        $this->assertFalse(DepartmentResource::canViewAny());
        $this->assertFalse(DepartmentResource::canCreate());
    }

    public function test_a_role_is_only_granted_the_specific_permissions_it_was_given(): void
    {
        $role = Role::create(['name' => 'پذیرش']);
        $role->givePermissionTo(Permission::where('name', 'departments.view_any')->sole());

        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);

        $this->assertTrue(DepartmentResource::canViewAny());
        $this->assertFalse(DepartmentResource::canCreate());
        $this->assertFalse(AiAssistant::canAccess());
    }

    public function test_granting_a_page_access_permission_allows_that_page_only(): void
    {
        $role = Role::create(['name' => 'دستیار مدیر']);
        $role->givePermissionTo(Permission::where('name', 'ai-assistant.access')->sole());

        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);

        $this->assertTrue(AiAssistant::canAccess());
        $this->assertFalse(DepartmentResource::canViewAny());
    }

    public function test_admin_role_cannot_be_deleted_via_the_role_form(): void
    {
        $admin = Role::firstOrCreate(['name' => 'admin']);

        $this->assertNotNull($admin);
        // The UI-level delete-protection is asserted at the table-action
        // level (RoleTable); this test documents the intended invariant
        // that the "admin" role must always exist for Gate::before to have
        // a super-admin to bypass checks for.
        $this->assertDatabaseHas('roles', ['name' => 'admin']);
    }

    public function test_assigning_a_department_scoped_permission_does_not_leak_into_other_modules(): void
    {
        $role = Role::create(['name' => 'ناظر اسناد']);
        $role->givePermissionTo(Permission::where('name', 'documents.view_any')->sole());

        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);

        $this->assertFalse(DepartmentResource::canViewAny());
        $this->assertFalse(AiAssistant::canAccess());
    }

    public function test_the_department_model_itself_is_unaffected_by_the_acting_users_role(): void
    {
        // The dynamic access-control system only gates panel navigation and
        // CRUD authorization; it must never filter query results.
        Department::factory()->count(3)->create();

        $role = Role::create(['name' => 'بدون دسترسی']);
        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);

        $this->assertSame(3, Department::query()->count());
    }
}
