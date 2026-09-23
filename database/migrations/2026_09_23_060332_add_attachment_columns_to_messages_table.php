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
        Schema::table('messages', function (Blueprint $table) {
            // A message can now be attachment-only (a file or voice note
            // with no caption), so body is no longer required.
            $table->text('body')->nullable()->change();

            $table->string('file_path')->nullable()->after('body');
            $table->string('file_name')->nullable()->after('file_path');
            $table->string('mime_type')->nullable()->after('file_name');
            $table->unsignedInteger('size_kb')->nullable()->after('mime_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn(['file_path', 'file_name', 'mime_type', 'size_kb']);
            $table->text('body')->nullable(false)->change();
        });
    }
};
