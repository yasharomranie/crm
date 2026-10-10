<?php

namespace App\Support;

use Filament\Support\Colors\Color;

/**
 * The fixed shape/color palettes offered by the avatar picker in the AI
 * Assistants Hub's "create assistant" form (see resources/views/filament/
 * pages/ai-assistants-hub.blade.php and the matching
 * x-ai-assistant-avatar Blade component). Colors are deliberately NOT new
 * hex values - every one is a Filament\Support\Colors\Color token already
 * used elsewhere in this panel (see AdminPanelProvider's
 * 'primary' => Color::Amber), so a custom assistant's avatar always reads
 * as "this app's own palette", never a generic AI purple/pink.
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
     * @var array<string, string> Color:: constant name => Persian label
     */
    public const COLORS = [
        'Amber' => 'کهربایی',
        'Orange' => 'نارنجی',
        'Red' => 'قرمز',
        'Pink' => 'صورتی',
        'Purple' => 'بنفش',
        'Indigo' => 'نیلی',
        'Blue' => 'آبی',
        'Teal' => 'فیروزه‌ای',
        'Emerald' => 'زمردی',
        'Green' => 'سبز',
    ];

    public static function isValidShape(string $shape): bool
    {
        return array_key_exists($shape, self::SHAPES);
    }

    public static function isValidColor(string $color): bool
    {
        return array_key_exists($color, self::COLORS);
    }

    /**
     * The oklch() color string for a given shade (50-950) of one of
     * COLORS' tokens, straight from Filament's own palette constants.
     */
    public static function shade(string $color, int $shade): string
    {
        $colorName = self::isValidColor($color) ? $color : 'Amber';

        /** @var array<int, string> $palette */
        $palette = constant(Color::class.'::'.$colorName);

        return $palette[$shade];
    }
}
