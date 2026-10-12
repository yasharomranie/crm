<?php

namespace Database\Factories;

use App\Enums\AiAssistantOcrUploadStatus;
use App\Models\AiAssistantOcrUpload;
use App\Models\AiAssistantProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiAssistantOcrUpload>
 */
class AiAssistantOcrUploadFactory extends Factory
{
    protected $model = AiAssistantOcrUpload::class;

    public function definition(): array
    {
        return [
            'ai_assistant_profile_id' => AiAssistantProfile::factory(),
            'original_filename' => fake()->word().'.pdf',
            'status' => AiAssistantOcrUploadStatus::Pending,
        ];
    }

    public function completed(int $noteCount = 3): self
    {
        return $this->state(['status' => AiAssistantOcrUploadStatus::Completed, 'note_count' => $noteCount]);
    }

    public function failed(string $errorMessage = 'خطای آزمایشی'): self
    {
        return $this->state(['status' => AiAssistantOcrUploadStatus::Failed, 'error_message' => $errorMessage]);
    }
}
