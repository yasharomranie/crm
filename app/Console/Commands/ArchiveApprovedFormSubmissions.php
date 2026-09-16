<?php

namespace App\Console\Commands;

use App\Enums\FormSubmissionStatus;
use App\Models\FormSubmission;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * One-off backfill for FormSubmission::archive(): every submission
 * approved before FormSubmissionObserver started archiving new ones
 * automatically has no Document yet - this walks all of them once so
 * "مدیریت اسناد" isn't missing everything that predates the feature.
 */
#[Signature('app:archive-approved-form-submissions')]
#[Description('Archive every already-Approved form submission that has no archived Document yet')]
class ArchiveApprovedFormSubmissions extends Command
{
    public function handle(): int
    {
        $submissions = FormSubmission::query()
            ->where('status', FormSubmissionStatus::Approved)
            ->whereDoesntHave('document')
            ->get();

        foreach ($submissions as $submission) {
            $submission->archive();
        }

        $this->info("{$submissions->count()} ثبت تأییدشده بایگانی شد.");

        return self::SUCCESS;
    }
}
