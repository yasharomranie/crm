<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            // Optional on purpose: a department is not required to belong
            // to a company (e.g. a shared/holding-level department), but
            // when it does, every record that already carries this
            // department's id (documents, projects, ...) is now
            // resolvable to a company through it.
            $table->foreignId('company_id')->nullable()->after('manager_id')
                ->constrained('companies')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('company_id');
        });
    }
};
