<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Soft-deletes form_fields so that removing a field from a form template no
 * longer hard-deletes the row - which, via form_submission_values.form_
 * field_id's cascadeOnDelete, used to permanently destroy every submitted
 * answer for that field across every past submission the moment an admin
 * removed it from the form builder. Soft-deleting never fires that cascade
 * (it's an UPDATE, not a DELETE), so historical answers now survive a
 * field being removed - see FormField::values() and FieldsRelationManager.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('form_fields', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('form_fields', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
