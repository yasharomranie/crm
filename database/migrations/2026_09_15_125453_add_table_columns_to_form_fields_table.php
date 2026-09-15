<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A FormFieldType::Table field's own column definitions and default row
 * count, in dedicated top-level columns rather than nested inside the
 * existing `options` JSON column (which already holds select/radio's flat
 * key-value options). Nesting them as `options.columns`/`options.
 * default_rows` was tried first, but Filament's Repeater silently loses
 * its item data (every key/label/type comes back null) when it - or a
 * sibling field - sits under a dotted sub-path of another component's own
 * state path; a plain top-level path doesn't have that problem, hence the
 * separate columns here instead of reusing `options`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('form_fields', function (Blueprint $table) {
            $table->json('table_columns')->nullable()->after('options');
            $table->unsignedInteger('table_default_rows')->nullable()->after('table_columns');
        });
    }

    public function down(): void
    {
        Schema::table('form_fields', function (Blueprint $table) {
            $table->dropColumn(['table_columns', 'table_default_rows']);
        });
    }
};
