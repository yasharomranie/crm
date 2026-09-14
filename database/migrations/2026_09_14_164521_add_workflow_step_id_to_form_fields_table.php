<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets a form field belong to a specific WorkflowStep instead of the form
 * in general - e.g. "نظر دفتر فنی" only gets filled in by whoever is
 * acting at the "تأیید دفتر فنی" step, not by the original submitter.
 * Null (the default, and every existing field) means "filled at initial
 * submission", exactly like before. See FormField::workflowStep() and
 * ViewFormSubmission's approve/reject actions.
 *
 * nullOnDelete(), not cascadeOnDelete(): deleting a workflow step should
 * turn its fields back into ordinary general fields, not destroy them -
 * consistent with form_fields' own soft-deletes and the rest of this
 * form-builder's now-established "never silently destroy submitted data"
 * rule.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('form_fields', function (Blueprint $table) {
            $table->foreignId('workflow_step_id')->nullable()->after('form_template_id')->constrained('workflow_steps')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('form_fields', function (Blueprint $table) {
            $table->dropConstrainedForeignId('workflow_step_id');
        });
    }
};
