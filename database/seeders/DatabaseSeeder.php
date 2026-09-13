<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $admin = User::factory()->create([
            'name' => 'مدیر سیستم',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
        ]);

        // The "admin" role bypasses per-document access-level checks in the
        // DMS (Document::isVisibleTo()/scopeVisibleTo()) so someone can
        // always administer every document regardless of department or
        // sharing.
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));
    }
}
