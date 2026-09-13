<?php

namespace App\Http\Controllers;

use App\Models\FormSubmission;
use App\Models\FormSubmissionValue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams a file attached to a form submission field, from the private
 * "local" disk - mirrors DocumentDownloadController: never served through
 * the public disk/symlink, so every download re-checks
 * FormSubmission::isVisibleTo() instead of trusting the URL alone.
 */
class FormSubmissionFileController extends Controller
{
    public function show(Request $request, FormSubmission $submission, FormSubmissionValue $value): StreamedResponse
    {
        abort_unless($submission->isVisibleTo($request->user()), 403, 'شما به این فرم دسترسی ندارید.');
        abort_unless($value->form_submission_id === $submission->id && $value->file_path, 404);

        return Storage::disk('local')->download($value->file_path);
    }
}
