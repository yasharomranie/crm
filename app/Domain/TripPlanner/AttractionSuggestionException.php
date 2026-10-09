<?php

namespace App\Domain\TripPlanner;

use Exception;

/**
 * Admin-facing (not customer-facing, unlike TripPlannerException) - shown
 * as a Filament notification when the AI attraction-suggestion action
 * fails.
 */
class AttractionSuggestionException extends Exception
{
    public static function invalidAiResponse(): self
    {
        return new self('هوش مصنوعی پاسخ معتبری برنگرداند. لطفاً دوباره تلاش کنید.');
    }
}
