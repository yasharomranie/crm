<?php

namespace App\Http\Controllers;

use App\Models\FormSubmission;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

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

        $submission->load($this->eagerLoads());

        return view('form-submissions.print', ['submission' => $submission]);
    }

    /**
     * A genuine one-click download - same dependency-free technique as
     * ContractPrintController::exportWord(): Word opens HTML served as a
     * .doc file natively.
     */
    public function exportWord(Request $request, FormSubmission $submission): Response
    {
        abort_unless($submission->isVisibleTo($request->user()), 403, 'شما به این فرم دسترسی ندارید.');

        $submission->load($this->eagerLoads());

        $html = view('form-submissions.word', ['submission' => $submission])->render();

        $title = "{$submission->formTemplate->title} - {$submission->reference_code}";
        $asciiFallback = Str::slug($submission->reference_code, '-') ?: 'form-submission';
        $utf8Filename = rawurlencode("{$title}.doc");

        return response($html, 200, [
            'Content-Type' => 'application/vnd.ms-word; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$asciiFallback}.doc\"; filename*=UTF-8''{$utf8Filename}",
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function eagerLoads(): array
    {
        return [
            'formTemplate.fields',
            'values.formField',
            'department.company',
            'submitter',
            'workflowInstance.workflowDefinition.steps',
            'workflowInstance.actions.actor',
        ];
    }
}
