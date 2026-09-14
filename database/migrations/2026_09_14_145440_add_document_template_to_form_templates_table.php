<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The optional "document template" a form template can carry: fixed
 * boilerplate text (rich HTML, authored with Filament's RichEditor)
 * with {{field_key}} placeholders sprinkled through it. When set, a
 * submission's print/Word/admin-view output merges real answers into
 * this text instead of showing a plain label/value list - see
 * FormSubmission::mergedDocumentBody().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('form_templates', function (Blueprint $table) {
            $table->longText('document_template')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('form_templates', function (Blueprint $table) {
            $table->dropColumn('document_template');
        });
    }
};
