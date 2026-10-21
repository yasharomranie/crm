<?php

namespace App\Support;

/**
 * The fixed shape/color palettes offered by the avatar picker in the AI
 * Assistants Hub's "create assistant" form (see resources/views/filament/
 * pages/ai-assistants-hub.blade.php and the matching
 * x-ai-assistant-avatar Blade component). The look is the soft "clay
 * blob" family: matte, rounded, vivid colours with two dark pill eyes. The
 * hex values below are the palette of that reference artwork and are shared
 * with the 3D team map (AiTeamMapData), so a profile has the same colour on
 * every screen.
 */
class AiAssistantAvatar
{
    /**
     * @var array<string, string> shape key => Persian label
     */
    public const SHAPES = [
        'circle' => 'دایره',
        'rounded-square' => 'مربع نرم',
        'hexagon' => 'شش‌ضلعی',
        'triangle' => 'مثلث',
        'teardrop' => 'قطره',
        'cloud' => 'ابر',
        'flower' => 'گل چهارپر',
        'diamond' => 'لوزی',
        'pentagon' => 'پنج‌ضلعی',
    ];

    /**
     * @var array<string, string> color key => Persian label (every key a profile may hold)
     */
    public const COLORS = [
        'Blue' => 'آبی',
        'Amber' => 'کهربایی',
        'Orange' => 'نارنجی',
        'Yellow' => 'زرد',
        'Red' => 'قرمز',
        'Pink' => 'صورتی',
        'Purple' => 'بنفش',
        'Brown' => 'قهوه‌ای',
        'Teal' => 'فیروزه‌ای',
        'White' => 'سفید',
        'Indigo' => 'نیلی',
        'Emerald' => 'زمردی',
        'Green' => 'سبز',
    ];

    /**
     * The colours the picker offers. The last three of COLORS are kept only
     * so assistants created earlier still render and validate.
     *
     * @var list<string>
     */
    public const PALETTE = ['Blue', 'Amber', 'Orange', 'Yellow', 'Red', 'Pink', 'Purple', 'Brown', 'Teal', 'White'];

    /**
     * @var array<string, string> color key => body colour
     */
    public const HEX = [
        'Blue' => '#2f7def',
        'Amber' => '#f5a33a',
        'Orange' => '#f26f2a',
        'Yellow' => '#f6b72b',
        'Red' => '#e8283a',
        'Pink' => '#ee2d86',
        'Purple' => '#8b52e8',
        'Brown' => '#a86f4b',
        'Teal' => '#3fb8a6',
        'White' => '#f3f3f1',
        'Indigo' => '#6e63f2',
        'Emerald' => '#34c77b',
        'Green' => '#4cc24a',
    ];

    /** The dark of the pill eyes - the same on every colour. */
    public const INK = '#1c1626';

    public static function isValidShape(string $shape): bool
    {
        return array_key_exists($shape, self::SHAPES);
    }

    public static function isValidColor(string $color): bool
    {
        return array_key_exists($color, self::COLORS);
    }

    public static function hex(string $color): string
    {
        return self::HEX[self::isValidColor($color) ? $color : 'Blue'];
    }

    /**
     * Blends two #rrggbb colours; $amount is how far to move from $hex to
     * $target (0 keeps $hex, 1 gives $target).
     */
    public static function mix(string $hex, string $target, float $amount): string
    {
        $from = sscanf($hex, '#%02x%02x%02x');
        $to = sscanf($target, '#%02x%02x%02x');

        return sprintf('#%02x%02x%02x', ...array_map(
            fn (int $a, int $b): int => (int) round($a + ($b - $a) * $amount),
            $from,
            $to,
        ));
    }
}
