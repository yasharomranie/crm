<?php

namespace App\Http\Controllers;

use App\Models\FormSubmission;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Renders a submitted form (values + full approval trail) as a clean,
 * print-ready page - the "خروجی PDF" the requirements doc asks for, using
 * the browser's own print-to-PDF instead of a new server-side PDF
 * dependency (the same approach already used for contracts).
 */
class FormSubmissionPrintController extends Controller
{
    public function show(Request $request, FormSubmission $submission): View
    {
        abort_unless($submission->isVisibleTo($request->user()), 403, 'شما به این فرم دسترسی ندارید.');

        $submission->load(['formTemplate.fields', 'values.formField', 'department', 'submitter', 'workflowInstance.workflowDefinition.steps', 'workflowInstance.actions.actor']);

        return view('form-submissions.print', ['submission' => $submission]);
    }
}
