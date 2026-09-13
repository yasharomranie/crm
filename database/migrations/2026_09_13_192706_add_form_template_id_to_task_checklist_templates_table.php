<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_checklist_templates', function (Blueprint $table) {
            $table->foreignId('form_template_id')->nullable()->after('recurrence')
                ->constrained('form_templates')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('task_checklist_templates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('form_template_id');
        });
    }
};
