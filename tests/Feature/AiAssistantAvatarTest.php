<?php

namespace Tests\Feature;

use App\Support\AiAssistantAvatar;
use Tests\TestCase;

/**
 * The avatars are soft clay blobs: a fixed colour palette and two dark pill
 * eyes, the same on every screen (hub, operations room, team map).
 */
class AiAssistantAvatarTest extends TestCase
{
    public function test_every_offered_colour_is_valid_and_has_a_body_colour(): void
    {
        foreach (AiAssistantAvatar::PALETTE as $color) {
            $this->assertTrue(AiAssistantAvatar::isValidColor($color), $color);
            $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/', AiAssistantAvatar::hex($color), $color);
        }
    }

    public function test_colours_from_older_assistants_still_work_but_are_not_offered(): void
    {
        foreach (['Indigo', 'Emerald', 'Green'] as $legacy) {
            $this->assertTrue(AiAssistantAvatar::isValidColor($legacy));
            $this->assertNotContains($legacy, AiAssistantAvatar::PALETTE);
            $this->assertNotSame(AiAssistantAvatar::hex('Blue'), AiAssistantAvatar::hex($legacy));
        }
    }

    public function test_an_unknown_colour_falls_back_to_blue(): void
    {
        $this->assertSame(AiAssistantAvatar::hex('Blue'), AiAssistantAvatar::hex('Chartreuse'));
    }

    public function test_mixing_moves_a_colour_toward_the_target(): void
    {
        $this->assertSame('#000000', AiAssistantAvatar::mix('#000000', '#ffffff', 0.0));
        $this->assertSame('#ffffff', AiAssistantAvatar::mix('#000000', '#ffffff', 1.0));
        $this->assertSame('#808080', AiAssistantAvatar::mix('#000000', '#ffffff', 0.5));
    }

    public function test_the_component_draws_the_body_colour_and_two_pill_eyes_without_a_mouth(): void
    {
        $html = (string) $this->blade('<x-ai-assistant-avatar shape="teardrop" color="Brown" />');

        $this->assertStringContainsString(AiAssistantAvatar::hex('Brown'), $html);
        $this->assertSame(2, substr_count($html, 'fill="'.AiAssistantAvatar::INK.'"'));
        $this->assertStringNotContainsString('<path d="M41,70', $html);
    }

    public function test_every_shape_renders_with_its_eyes(): void
    {
        foreach (array_keys(AiAssistantAvatar::SHAPES) as $shape) {
            $html = (string) $this->blade('<x-ai-assistant-avatar :shape="$shape" color="Teal" />', ['shape' => $shape]);

            $this->assertSame(2, substr_count($html, 'fill="'.AiAssistantAvatar::INK.'"'), $shape);
        }
    }
}
