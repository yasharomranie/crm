<?php

namespace App\Domain\TripPlanner;

use App\Enums\AttractionCategory;
use App\Exceptions\AiProviderException;
use App\Models\AiProvider;
use App\Models\Attraction;

/**
 * Lets an admin generate a batch of candidate attractions with the AI
 * instead of typing every field by hand - same direct-chatCompletion
 * pattern as TripPlannerService (no tool-calling engine, one structured
 * JSON request). The AI never suggests coordinates: the admin panel keeps
 * every suggestion as an editable preview before anything is created, and
 * created rows stay inactive with no lat/lng until an admin pins the real
 * location (see Attraction's own doc comment and AttractionForm).
 */
class AttractionAiSuggestionService
{
    /**
     * @return array<int, array{name: string, category: string, description: ?string, address: ?string, avg_visit_minutes: int, opens_at: ?string, closes_at: ?string}>
     */
    public function suggestMany(int $count, ?string $focus = null): array
    {
        $count = max(1, min(10, $count));

        $provider = AiProvider::active();

        if (! $provider) {
            throw AiProviderException::noActiveProvider();
        }

        $existingNames = Attraction::pluck('name')->all();

        $message = $provider->chatCompletion([
            ['role' => 'system', 'content' => $this->systemPrompt()],
            ['role' => 'user', 'content' => $this->userPrompt($count, $focus, $existingNames)],
        ]);

        return $this->parseAndValidate($message['content'] ?? '', $existingNames);
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
            شما یک کارشناس گردشگری همدان (ایران) هستید که به تیم ادمین یک اپلیکیشن گردشگری کمک می‌کنید جاذبه‌های واقعی و شناخته‌شده را برای ثبت در دیتابیس پیشنهاد دهد.
            فقط و فقط یک آرایه JSON معتبر برگردان - بدون هیچ متن اضافه، بدون ```، دقیقاً با این ساختار:
            [{"name": "...", "category": "nature|historical|adventure|shopping|food|religious|family", "description": "یک پاراگراف کوتاه و واقعی درباره این مکان", "address": "آدرس تقریبی یا نام منطقه", "avg_visit_minutes": 60, "opens_at": "08:00", "closes_at": "18:00"}]

            قوانین اجباری:
            - فقط جاهای واقعی و شناخته‌شده در همدان یا نزدیکی آن پیشنهاد بده - هیچ مکان تخیلی یا نامطمئن نساز.
            - هیچ‌کدام از نام‌هایی که در لیست «جاهای موجود» داده شده را دوباره پیشنهاد نده.
            - category باید دقیقاً یکی از مقادیر داده‌شده باشد.
            - اگر ساعت کاری مشخصی ندارد (مثلاً یک میدان یا پارک عمومی)، opens_at و closes_at را null بگذار.
            - avg_visit_minutes باید عددی واقع‌بینانه بین ۱۵ تا ۲۴۰ دقیقه باشد.
            - هرگز مختصات جغرافیایی پیشنهاد نده - این فیلد در ساختار خروجی اصلاً وجود ندارد.
            PROMPT;
    }

    /**
     * @param  array<int, string>  $existingNames
     */
    private function userPrompt(int $count, ?string $focus, array $existingNames): string
    {
        $lines = [
            "تعداد پیشنهاد مورد نیاز: {$count}",
        ];

        if ($focus) {
            $lines[] = "تمرکز درخواستی ادمین: {$focus}";
        }

        $lines[] = 'جاهای موجود (دوباره پیشنهاد نده):';
        $lines[] = $existingNames ? implode('، ', $existingNames) : 'فعلاً هیچ جایی ثبت نشده است.';

        return implode("\n", $lines);
    }

    /**
     * @param  array<int, string>  $existingNames
     * @return array<int, array{name: string, category: string, description: ?string, address: ?string, avg_visit_minutes: int, opens_at: ?string, closes_at: ?string}>
     */
    private function parseAndValidate(string $content, array $existingNames): array
    {
        $stripped = trim(preg_replace('/^```(?:json)?|```$/m', '', trim($content)));

        $data = json_decode($stripped, true);

        if (! is_array($data)) {
            throw AttractionSuggestionException::invalidAiResponse();
        }

        $existingNamesLower = array_map(mb_strtolower(...), $existingNames);
        $validCategories = array_column(AttractionCategory::cases(), 'value');

        $items = [];

        foreach ($data as $row) {
            if (! is_array($row) || blank($row['name'] ?? null)) {
                continue;
            }

            if (in_array(mb_strtolower((string) $row['name']), $existingNamesLower, true)) {
                continue;
            }

            $items[] = [
                'name' => (string) $row['name'],
                'category' => in_array($row['category'] ?? null, $validCategories, true)
                    ? $row['category']
                    : AttractionCategory::Historical->value,
                'description' => $row['description'] ?? null,
                'address' => $row['address'] ?? null,
                'avg_visit_minutes' => max(15, min(240, (int) ($row['avg_visit_minutes'] ?? 60))),
                'opens_at' => $row['opens_at'] ?? null,
                'closes_at' => $row['closes_at'] ?? null,
            ];
        }

        return $items;
    }
}
