<?php

namespace App\Jobs;

use App\Domain\Ai\KnowledgeNoteExtractor;
use App\Domain\Ai\ScannedPdfOcrExtractor;
use App\Models\AiAssistantProfile;
use App\Models\AiProvider;
use App\Models\User;
use App\Notifications\KnowledgeDocumentOcrCompletedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

/**
 * Finishes what HasCustomAssistantChatEngine::processKnowledgeUpload()
 * starts for a PDF DocumentTextExtractor found no text in at all - almost
 * always a scan (a photographed/scanned document with no text layer, as
 * opposed to a digitally-generated PDF). OCR runs a couple of seconds per
 * page, so it has no place inline in the web request that dispatches
 * this job; reuses KnowledgeNoteExtractor, the same chunked
 * note-extraction step the fast synchronous path uses, so a scanned
 * document ends up going through identical AI processing once its text
 * is in hand.
 */
class OcrKnowledgeUploadJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    /**
     * ScannedPdfOcrExtractor::MAX_OCR_PAGES pages at a couple of seconds
     * each, plus the chunked note-extraction calls after - generous
     * headroom over the worst case.
     */
    public int $timeout = 900;

    public function __construct(
        public int $aiAssistantProfileId,
        public int $aiProviderId,
        public string $storedPath,
        public ?int $userId,
    ) {}

    public function handle(ScannedPdfOcrExtractor $ocrExtractor, KnowledgeNoteExtractor $noteExtractor): void
    {
        try {
            $profile = AiAssistantProfile::find($this->aiAssistantProfileId);
            $provider = AiProvider::find($this->aiProviderId);

            if (! $profile || ! $provider) {
                return;
            }

            $extraction = $ocrExtractor->extract(Storage::path($this->storedPath));

            if (isset($extraction['error'])) {
                $this->notifyOwner($profile, 0, $extraction['error']);

                return;
            }

            $result = $noteExtractor->extract($profile, $provider, $extraction['content']);

            foreach ($result['notes'] as $note) {
                $profile->recordAiSuggestedKnowledge($note);
            }

            $this->notifyOwner($profile, count($result['notes']), $result['error']);
        } finally {
            Storage::delete($this->storedPath);
        }
    }

    private function notifyOwner(AiAssistantProfile $profile, int $noteCount, ?string $error): void
    {
        $owner = $this->userId ? User::find($this->userId) : $profile->creator;

        $owner?->notify(new KnowledgeDocumentOcrCompletedNotification($profile, $noteCount, $error));
    }
}
