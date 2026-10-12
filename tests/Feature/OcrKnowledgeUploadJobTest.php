<?php

namespace Tests\Feature;

use App\Domain\Ai\KnowledgeNoteExtractor;
use App\Domain\Ai\ScannedPdfOcrExtractor;
use App\Enums\AiAssistantOcrUploadStatus;
use App\Jobs\OcrKnowledgeUploadJob;
use App\Models\AiAssistantKnowledgeEntry;
use App\Models\AiAssistantOcrUpload;
use App\Models\AiAssistantProfile;
use App\Models\AiProvider;
use App\Models\User;
use App\Notifications\KnowledgeDocumentOcrCompletedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsScannedPdfFixtures;
use Tests\TestCase;

class OcrKnowledgeUploadJobTest extends TestCase
{
    use BuildsScannedPdfFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessOcrToolsAvailable();
    }

    public function test_it_ocrs_the_stored_file_and_records_pending_knowledge_entries(): void
    {
        Notification::fake();
        Storage::fake();

        $provider = AiProvider::factory()->active()->create();
        $owner = User::factory()->create();
        $profile = AiAssistantProfile::factory()->create(['created_by' => $owner->id]);
        $ocrUpload = AiAssistantOcrUpload::factory()->for($profile, 'profile')->create();

        $localPath = sys_get_temp_dir().'/ocr-job-test-'.uniqid().'.pdf';
        $this->buildScannedPdf($localPath, ['Commission rate is five percent.']);
        $storedPath = 'knowledge-ocr-uploads/'.basename($localPath);
        Storage::put($storedPath, file_get_contents($localPath));
        unlink($localPath);

        Http::fake([
            '*/chat/completions' => Http::response([
                'choices' => [[
                    'message' => [
                        'role' => 'assistant',
                        'content' => json_encode(['نرخ کمیسیون پنج درصد است.']),
                    ],
                ]],
            ]),
        ]);

        (new OcrKnowledgeUploadJob($ocrUpload->id, $provider->id, $storedPath, $owner->id))->handle(
            app(ScannedPdfOcrExtractor::class),
            app(KnowledgeNoteExtractor::class),
        );

        $entry = AiAssistantKnowledgeEntry::sole();
        $this->assertSame('نرخ کمیسیون پنج درصد است.', $entry->content);
        $this->assertSame($profile->id, $entry->ai_assistant_profile_id);

        $ocrUpload->refresh();
        $this->assertSame(AiAssistantOcrUploadStatus::Completed, $ocrUpload->status);
        $this->assertSame(1, $ocrUpload->note_count);
        $this->assertNull($ocrUpload->error_message);

        Storage::assertMissing($storedPath);

        Notification::assertSentTo($owner, KnowledgeDocumentOcrCompletedNotification::class);
    }

    public function test_it_notifies_the_owner_without_creating_entries_when_ocr_finds_nothing(): void
    {
        Notification::fake();
        Storage::fake();

        $provider = AiProvider::factory()->active()->create();
        $owner = User::factory()->create();
        $profile = AiAssistantProfile::factory()->create(['created_by' => $owner->id]);
        $ocrUpload = AiAssistantOcrUpload::factory()->for($profile, 'profile')->create();

        $localPath = sys_get_temp_dir().'/ocr-job-test-blank-'.uniqid().'.pdf';
        file_put_contents($localPath, 'not a real pdf');
        $storedPath = 'knowledge-ocr-uploads/'.basename($localPath);
        Storage::put($storedPath, file_get_contents($localPath));
        unlink($localPath);

        Http::fake();

        (new OcrKnowledgeUploadJob($ocrUpload->id, $provider->id, $storedPath, $owner->id))->handle(
            app(ScannedPdfOcrExtractor::class),
            app(KnowledgeNoteExtractor::class),
        );

        $this->assertSame(0, AiAssistantKnowledgeEntry::count());
        Http::assertNothingSent();
        Storage::assertMissing($storedPath);

        $ocrUpload->refresh();
        $this->assertSame(AiAssistantOcrUploadStatus::Failed, $ocrUpload->status);
        $this->assertNotNull($ocrUpload->error_message);

        Notification::assertSentTo($owner, KnowledgeDocumentOcrCompletedNotification::class);
    }
}
