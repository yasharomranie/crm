<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('digital_projects', function (Blueprint $table) {
            $table->timestamp('alerted_at')->nullable();
        });

        Schema::table('digital_project_actions', function (Blueprint $table) {
            $table->timestamp('alerted_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('digital_project_actions', function (Blueprint $table) {
            $table->dropColumn('alerted_at');
        });

        Schema::table('digital_projects', function (Blueprint $table) {
            $table->dropColumn('alerted_at');
        });
    }
};
