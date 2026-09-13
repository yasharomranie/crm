<?php

namespace App\Enums;

/**
 * Mirrors the "role" field of the OpenAI-compatible chat schema. Not shown
 * in any Filament form/table, so unlike most enums in this app it doesn't
 * implement HasLabel/HasColor.
 */
enum AiMessageRole: string
{
    case User = 'user';
    case Assistant = 'assistant';
    case Tool = 'tool';
    case System = 'system';
}
