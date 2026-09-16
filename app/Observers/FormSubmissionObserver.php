<?php

namespace App\Observers;

use App\Enums\FormSubmissionStatus;
use App\Models\FormSubmission;

/**
 * Archives a submission into "مدیریت اسناد" the moment it reaches its
 * final Approved state - whether that happens instantly (no workflow, see
 * FormSubmission::submit()) or after a multi-step approval finishes (see
 * WorkflowInstance::act()), both paths just update() the submission's
 * status, so this single hook covers either one.
 */
class FormSubmissionObserver
{
    public function updated(FormSubmission $submission): void
    {
        if ($submission->wasChanged('status') && $submission->status === FormSubmissionStatus::Approved) {
            $submission->archive();
        }
    }
}
