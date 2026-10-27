<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * A host where a table or column already exists (an earlier run created it but
 * never recorded the migration) must still be able to run the rest.
 */
class DigitalProjectMigrationsTest extends TestCase
{
    use RefreshDatabase;

    private function run_migration(string $file): void
    {
        (require database_path("migrations/{$file}"))->up();
    }

    public function test_the_stats_table_migration_can_run_when_the_table_already_exists(): void
    {
        $this->assertTrue(Schema::hasTable('digital_project_stats'));

        $this->run_migration('2026_11_17_100000_create_digital_project_stats_table.php');

        $this->assertTrue(Schema::hasTable('digital_project_stats'));
    }

    public function test_the_work_category_migration_can_run_when_the_columns_already_exist(): void
    {
        $this->run_migration('2026_11_18_100000_add_work_category_to_digital_project_tables.php');

        $this->assertTrue(Schema::hasColumn('digital_project_logs', 'work_category'));
        $this->assertTrue(Schema::hasColumn('digital_project_actions', 'work_category'));
    }
}
