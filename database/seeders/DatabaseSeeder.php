<?php

namespace Database\Seeders;

use App\Models\AiProvider;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Make sure every module defined in config/access-control.php has
        // its permissions ready before any role gets configured against
        // them (also safe to re-run after the config file changes).
        Artisan::call('access-control:sync');

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

        // A pre-filled (but not functional) GapGPT entry so "تنظیمات هوش
        // مصنوعی" isn't empty on first run - the base_url is unverified
        // (couldn't be fetched from gapgpt.app/platform-v2/docs/quickstart
        // in this environment) and the api_key is a placeholder; the user
        // must paste their real key and confirm the base_url before the AI
        // assistant will actually work.
        AiProvider::firstOrCreate(['name' => 'GapGPT'], [
            'base_url' => 'https://api.gapgpt.app/v1',
            'api_key' => 'REPLACE_WITH_YOUR_GAPGPT_API_KEY',
            'default_model' => 'gpt-4o-mini',
            'is_active' => true,
        ]);

        $this->call(WebAppContentSeeder::class);
        $this->call(AttractionSeeder::class);
    }
}
