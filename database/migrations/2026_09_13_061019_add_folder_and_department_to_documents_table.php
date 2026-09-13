<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->foreignId('folder_id')->nullable()->after('id')->constrained('document_folders')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->after('access_level')->constrained('departments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('folder_id');
            $table->dropConstrainedForeignId('department_id');
        });
    }
};
