<?php

namespace App\Console\Commands;

use App\Domain\Ai\AssistantEvalRunner;
use App\Exceptions\AiProviderException;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('ai-assistant:eval {--user= : User id to run as (default: the first admin)} {--limit= : Only the first N questions} {--min=80 : Fail below this pass percentage}')]
#[Description('Ask the active AI provider the fixed evaluation questions and report how often it picks a right first tool')]
class EvaluateAiAssistant extends Command
{
    public function handle(AssistantEvalRunner $runner): int
    {
        $user = $this->option('user')
            ? User::find($this->option('user'))
            : User::query()->whereHas('roles', fn ($query) => $query->where('name', 'admin'))->first();

        if (! $user) {
            $this->error('No user to run the evaluation as (give --user or create an admin).');

            return self::FAILURE;
        }

        try {
            $report = $runner->run($user, $this->option('limit') ? (int) $this->option('limit') : null);
        } catch (AiProviderException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($report === null) {
            $this->error('No active AI provider is configured.');

            return self::FAILURE;
        }

        $this->info("{$report['passed']} of {$report['total']} questions got a right first tool ({$report['percent']}%).");

        foreach ($report['failures'] as $failure) {
            $this->line(sprintf(
                '- %s | expected: %s | chosen: %s%s',
                $failure['question'],
                implode(', ', $failure['expected']) ?: '(no tool)',
                implode(', ', $failure['chosen']) ?: '(no tool)',
                $failure['expected_was_offered'] ? '' : ' | the right tool was NOT offered (routing)',
            ));
        }

        return $report['percent'] >= (int) $this->option('min') ? self::SUCCESS : self::FAILURE;
    }
}
