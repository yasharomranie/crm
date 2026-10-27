<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['digital_project_logs', 'digital_project_actions'] as $tableName) {
            if (Schema::hasColumn($tableName, 'work_category')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) {
                $table->string('work_category')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::table('digital_project_actions', function (Blueprint $table) {
            $table->dropColumn('work_category');
        });

        Schema::table('digital_project_logs', function (Blueprint $table) {
            $table->dropColumn('work_category');
        });
    }
};
