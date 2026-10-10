<?php

namespace Database\Factories;

use App\Enums\AiKnowledgeEntrySource;
use App\Enums\AiKnowledgeEntryStatus;
use App\Models\AiAssistantKnowledgeEntry;
use App\Models\AiAssistantProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiAssistantKnowledgeEntry>
 */
class AiAssistantKnowledgeEntryFactory extends Factory
{
    protected $model = AiAssistantKnowledgeEntry::class;

    public function definition(): array
    {
        return [
            'ai_assistant_profile_id' => AiAssistantProfile::factory(),
            'content' => fake()->sentence(10),
            'status' => AiKnowledgeEntryStatus::Approved,
            'source' => AiKnowledgeEntrySource::Manual,
        ];
    }

    public function pending(): self
    {
        return $this->state(['status' => AiKnowledgeEntryStatus::Pending, 'source' => AiKnowledgeEntrySource::AiSuggested]);
    }
}
