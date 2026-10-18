<?php

namespace App\Domain\Ai;

use App\Exceptions\AiProviderException;
use App\Models\User;

/**
 * A regression check for the assistant's prompt, tool descriptions and
 * routing: a fixed set of manager questions (resources/ai-eval/questions.json)
 * with the tool(s) that would be a right first move. It asks the real model
 * once per question, with no tool executed, and reports how many it got
 * right and - for each miss - whether the right tool was even offered.
 */
class AssistantEvalRunner
{
    public function __construct(private readonly HeadlessAssistant $assistant) {}

    /**
     * @return list<array{question: string, expect: list<string>}>
     */
    public static function questions(): array
    {
        return json_decode((string) file_get_contents(resource_path('ai-eval/questions.json')), true) ?: [];
    }

    /**
     * @return array{total: int, passed: int, percent: int, failures: list<array{question: string, expected: list<string>, chosen: list<string>, expected_was_offered: bool}>}|null null when no provider is set up
     *
     * @throws AiProviderException
     */
    public function run(User $user, ?int $limit = null): ?array
    {
        $questions = array_slice(self::questions(), 0, $limit ?: null);
        $passed = 0;
        $failures = [];

        foreach ($questions as $case) {
            $result = $this->assistant->chooseTools($user, $case['question']);

            if ($result === null) {
                return null;
            }

            $ok = $case['expect'] === []
                ? $result['chosen'] === []
                : array_intersect($result['chosen'], $case['expect']) !== [];

            if ($ok) {
                $passed++;

                continue;
            }

            $failures[] = [
                'question' => $case['question'],
                'expected' => $case['expect'],
                'chosen' => $result['chosen'],
                'expected_was_offered' => $case['expect'] === [] || array_intersect($result['offered'], $case['expect']) !== [],
            ];
        }

        $total = count($questions);

        return [
            'total' => $total,
            'passed' => $passed,
            'percent' => $total > 0 ? (int) round($passed / $total * 100) : 100,
            'failures' => $failures,
        ];
    }
}
