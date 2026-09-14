<?php

namespace App\Http\Controllers;

use App\Models\FormSubmission;
use App\Models\FormSubmissionValue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams a file attached to a form submission field, from the app's
 * private default disk - never served through the public disk/symlink, so
 * every download re-checks FormSubmission::isVisibleTo() instead of
 * trusting the URL alone.
 *
 * The disk is whatever config/filesystems.php resolves as default (plain
 * "local" in dev; a private Cloud Object Storage bucket in production) -
 * Storage::disk() with no argument, never Storage::disk('local') by name.
 * Hardcoding 'local' here is what made uploaded files 404 in production:
 * that disk is this app instance's own ephemeral filesystem, not shared
 * across replicas or kept across deploys, so a file saved by the instance
 * that handled the upload is simply gone by the time a download request
 * lands on a different instance.
 */
class FormSubmissionFileController extends Controller
{
    public function show(Request $request, FormSubmission $submission, FormSubmissionValue $value): StreamedResponse
    {
        abort_unless($submission->isVisibleTo($request->user()), 403, 'شما به این فرم دسترسی ندارید.');
        abort_unless($value->form_submission_id === $submission->id && $value->file_path, 404);

        return Storage::disk()->download($value->file_path);
    }
}
