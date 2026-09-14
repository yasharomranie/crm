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
 * Storage::disk() with no argument, never Storage::disk('local') by name -
 * on a host that scales to more than one app instance (or on ephemeral
 * compute like Laravel Cloud), the 'local' disk is that one instance's own
 * throwaway filesystem, so a hardcoded name can leave uploads unreadable
 * from any other instance. Harmless on a single traditional server, but
 * costs nothing to get right either way.
 */
class FormSubmissionFileController extends Controller
{
    public function show(Request $request, FormSubmission $submission, FormSubmissionValue $value): StreamedResponse
    {
        abort_unless($submission->isVisibleTo($request->user()), 403, 'شما به این فرم دسترسی ندارید.');

        // ((int) ... === (int) ...), not a bare ===: FormSubmissionValue
        // casts form_submission_id to int now, but this comparison used to
        // rely on Eloquent returning it as a native int - which some
        // MySQL/PDO driver configurations don't do on their own, silently
        // 404ing a perfectly real, correctly stored file the moment
        // form_submission_id came back as the string "11" instead of the
        // int 11. Casting both sides explicitly here means this check
        // can't regress that way again even if a future cast gets removed.
        abort_unless((int) $value->form_submission_id === (int) $submission->id && $value->file_path, 404);

        return Storage::disk()->download($value->file_path);
    }
}
