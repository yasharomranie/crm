<?php

namespace App\Enums;

/**
 * Where a knowledge-base entry (App\Models\AiAssistantKnowledgeEntry) came
 * from - typed directly by the profile's creator (Manual, always created
 * already Approved), or proposed by the assistant itself mid-conversation
 * via the save_knowledge_entry tool (AiSuggested, Pending unless the
 * profile has knowledge_auto_save on).
 */
enum AiKnowledgeEntrySource: string
{
    case Manual = 'manual';
    case AiSuggested = 'ai_suggested';
}
