<?php

namespace App\Jobs;

use App\Domain\Ai\Missions\MissionSourceDigester;
use App\Enums\AiMissionSourceStatus;
use App\Models\AiMissionSource;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/** Reads and digests one library file in the background. */
class ProcessMissionSourceJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 280;

    public function __construct(public int $sourceId) {}

    public function handle(MissionSourceDigester $digester): void
    {
        if ($source = AiMissionSource::find($this->sourceId)) {
            $digester->process($source);
        }
    }

    public function failed(Throwable $exception): void
    {
        AiMissionSource::whereKey($this->sourceId)->update(['status' => AiMissionSourceStatus::Failed->value, 'error' => 'خواندن فایل با خطا متوقف شد.']);
    }
}
