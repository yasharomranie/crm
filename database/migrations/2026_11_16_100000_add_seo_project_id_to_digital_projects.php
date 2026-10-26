<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('digital_projects', function (Blueprint $table) {
            $table->foreignId('seo_project_id')->nullable()->constrained('seo_projects')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('digital_projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('seo_project_id');
        });
    }
};
