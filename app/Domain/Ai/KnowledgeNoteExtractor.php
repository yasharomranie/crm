<?php

namespace App\Domain\Ai;

use App\Exceptions\AiProviderException;
use App\Models\AiAssistantProfile;
use App\Models\AiProvider;

/**
 * Turns raw document text (from DocumentTextExtractor or
 * ScannedPdfOcrExtractor) into a handful of short, stand-alone knowledge
 * notes, via the active AiProvider. Shared by both the fast synchronous
 * upload path (HasCustomAssistantChatEngine::processKnowledgeUpload) and
 * the OCR path (App\Jobs\OcrKnowledgeUploadJob), which otherwise would
 * have had to duplicate this chunking logic - a web request and a queued
 * job disagree on how to surface a failure (a Filament toast vs nothing
 * to toast to), so this returns the error instead of displaying it,
 * leaving that choice to the caller.
 *
 * A single chat-completion call only ever sees the start of a long
 * document, so walking it in fixed-size chunks and calling the AI once
 * per chunk is what lets notes get pulled from the whole thing instead
 * of just its first few pages.
 */
class KnowledgeNoteExtractor
{
    /**
     * Characters per chat-completion call - small enough to stay well
     * inside any provider's context window, large enough that a 120-page
     * document only needs a handful of calls.
     */
    private const CHUNK_SIZE = 40000;

    /**
     * Across all chunks of one document, so a very long upload still
     * yields a reviewable number of notes instead of flooding the queue.
     */
    private const MAX_NOTES = 30;

    /**
     * @return array{notes: list<string>, error: ?string} error is set
     *                                                    only when the provider call itself failed - a
     *                                                    successful call that simply found nothing useful
     *                                                    returns an empty notes list with no error.
     */
    public function extract(AiAssistantProfile $profile, AiProvider $provider, string $text): array
    {
        $notes = collect();
        $error = null;

        foreach (mb_str_split($text, self::CHUNK_SIZE) as $chunk) {
            try {
                $notes = $notes->merge($this->requestNotesForChunk($profile, $provider, $chunk));
            } catch (AiProviderException $exception) {
                // A provider that's down will fail identically on every
                // remaining chunk - stop rather than retry a dead
                // provider N more times, but keep whatever chunks before
                // this one already succeeded.
                $error = $exception->getMessage();
                break;
            }
        }

        return [
            'notes' => $notes->unique()->take(self::MAX_NOTES)->values()->all(),
            'error' => $error,
        ];
    }

    /**
     * @return list<string>
     */
    private function requestNotesForChunk(AiAssistantProfile $profile, AiProvider $provider, string $text): array
    {
        $messages = [
            [
                'role' => 'system',
                'content' => 'تو یک دستیار استخراج اطلاعاتی. از متن کاربر، مهم‌ترین نکات مستقل و قابل‌استفاده را استخراج کن (حداکثر ۱۵ نکته، هر نکته کوتاه و به فارسی). پاسخ تو باید فقط و فقط یک آرایهٔ JSON از رشته‌ها باشد، بدون هیچ توضیح یا متن اضافه.',
            ],
            ['role' => 'user', 'content' => $text],
        ];

        $response = $provider->chatCompletion($messages, [], $profile->model);

        $notes = json_decode($this->stripJsonFences((string) ($response['content'] ?? '')), true);

        if (! is_array($notes)) {
            return [];
        }

        return collect($notes)
            ->map(fn ($note) => trim((string) $note))
            ->filter(fn (string $note) => $note !== '')
            ->take(15)
            ->values()
            ->all();
    }

    /**
     * Some providers wrap a JSON response in a ```json ... ``` fence even
     * when told not to - strip it before decoding.
     */
    private function stripJsonFences(string $content): string
    {
        $content = trim($content);
        $content = preg_replace('/^```(?:json)?/i', '', $content) ?? $content;
        $content = preg_replace('/```$/', '', $content) ?? $content;

        return trim($content);
    }
}
