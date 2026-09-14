<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * form_fields is soft-deleted now (see the deleted_at migration), so a
 * plain unique(form_template_id, field_key) index would block recreating a
 * field with the same key as one that was only *soft*-deleted - exactly
 * the "delete it, then rebuild it" workflow that caused this whole change.
 * Uniqueness among active fields is now enforced at the application layer
 * instead (FieldsRelationManager::form() - a scopedUnique() check, which
 * naturally only looks at non-trashed rows).
 *
 * MySQL/InnoDB was using this unique index as the supporting index for the
 * form_template_id foreign key (its leftmost column), so it refuses to
 * drop it while that FK exists and no other index covers the column - a
 * plain index on form_template_id alone is added first to take over that
 * job before the unique index is dropped.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('form_fields', function (Blueprint $table) {
            $table->index('form_template_id');
        });

        Schema::table('form_fields', function (Blueprint $table) {
            $table->dropUnique(['form_template_id', 'field_key']);
        });
    }

    public function down(): void
    {
        Schema::table('form_fields', function (Blueprint $table) {
            $table->unique(['form_template_id', 'field_key']);
        });

        Schema::table('form_fields', function (Blueprint $table) {
            $table->dropIndex(['form_template_id']);
        });
    }
};
