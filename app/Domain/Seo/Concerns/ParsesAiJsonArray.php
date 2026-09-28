<?php

namespace App\Domain\Seo\Concerns;

/**
 * Shared by every AI-backed SEO service that expects a JSON array back
 * from AiProvider::chatCompletion() (AiKeywordClassifier,
 * AiKeywordDiscoveryService) - keeps the "models sometimes wrap JSON in a
 * markdown fence despite being told not to" tolerance in one place.
 */
trait ParsesAiJsonArray
{
    /**
     * @return array<int, array<string, mixed>>
     */
    private function parseJsonArray(string $content): array
    {
        $content = trim(preg_replace('/^```(?:json)?\s*|\s*```$/m', '', trim($content)));

        $decoded = json_decode($content, true);

        return is_array($decoded) ? $decoded : [];
    }
}
