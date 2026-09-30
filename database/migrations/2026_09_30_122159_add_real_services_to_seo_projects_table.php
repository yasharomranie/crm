<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('seo_projects', function (Blueprint $table) {
            $table->text('real_services')->nullable()->after('audience');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('seo_projects', function (Blueprint $table) {
            $table->dropColumn('real_services');
        });
    }
};
