<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * form_submissions.form_template_id used to cascadeOnDelete() - deleting a
 * FormTemplate ("فرم مادر") silently destroyed every submission ever made
 * with it, and every one of THOSE submissions' values via their own
 * cascade. Switched to restrictOnDelete(): the database now refuses to
 * delete a form template while any submission still references it, so a
 * template with real history simply can't be deleted out from under its
 * data. See EditFormTemplate for the friendly, pre-checked version of that
 * same rule shown in the UI before the database ever has to enforce it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('form_submissions', function (Blueprint $table) {
            $table->dropForeign(['form_template_id']);
        });

        Schema::table('form_submissions', function (Blueprint $table) {
            $table->foreign('form_template_id')->references('id')->on('form_templates')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('form_submissions', function (Blueprint $table) {
            $table->dropForeign(['form_template_id']);
        });

        Schema::table('form_submissions', function (Blueprint $table) {
            $table->foreign('form_template_id')->references('id')->on('form_templates')->cascadeOnDelete();
        });
    }
};
