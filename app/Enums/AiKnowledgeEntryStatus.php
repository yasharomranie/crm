<?php

namespace App\Enums;

/**
 * Whether a knowledge-base entry (App\Models\AiAssistantKnowledgeEntry) is
 * already feeding the assistant's system prompt (Approved) or still
 * awaiting the profile creator's one-tap review (Pending). Not shown in
 * any Filament form/table, so unlike most enums in this app it doesn't
 * implement HasLabel/HasColor.
 */
enum AiKnowledgeEntryStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
}
