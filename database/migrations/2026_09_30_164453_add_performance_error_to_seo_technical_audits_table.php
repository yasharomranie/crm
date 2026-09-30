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
        Schema::table('seo_technical_audits', function (Blueprint $table) {
            $table->text('performance_error')->nullable()->after('core_web_vitals');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('seo_technical_audits', function (Blueprint $table) {
            $table->dropColumn('performance_error');
        });
    }
};
