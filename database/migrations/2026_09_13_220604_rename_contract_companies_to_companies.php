<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "شرکت‌های ما (طرف کارفرما)" was contract-only until now; it becomes the
 * organization's central "شرکت‌ها" (holding companies) registry, reused by
 * departments and - through them - anything that already carries a
 * department_id (documents, projects, ...), not just contracts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('contract_companies', 'companies');

        Schema::table('contracts', function (Blueprint $table) {
            $table->renameColumn('contract_company_id', 'company_id');
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->renameColumn('company_id', 'contract_company_id');
        });

        Schema::rename('companies', 'contract_companies');
    }
};
