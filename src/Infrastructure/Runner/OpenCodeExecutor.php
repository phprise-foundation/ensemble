<?php

declare(strict_types=1);

namespace App\Infrastructure\Runner;

use App\Domain\Runner\AgentExecutor;
use App\Domain\Runner\AgentRunContext;
use App\Domain\Runner\Outcome;
use App\Domain\Team\Agent;
use Override;

final readonly class OpenCodeExecutor implements AgentExecutor
{
    private const DEFAULT_TIMEOUT_SECONDS = 3600;

    public function __construct(
        private int $timeoutSeconds = self::DEFAULT_TIMEOUT_SECONDS,
    ) {
    }

    #[Override]
    public function execute(
        Agent $agent,
        string $workdirPath,
        string $prompt,
        AgentRunContext $context,
    ): Outcome
    {
        $failure = 'no model is configured for this agent';
        foreach ([$agent->model(), $agent->fallbackModel()] as $model) {
            if ($model === '') {
                continue;
            }

            $result = $this->run(['opencode', 'run', '--model', $model, $prompt], $workdirPath, $context);
            if ($result['exit'] === 0) {
                return Outcome::succeeded($this->trim($result['output']));
            }

            $failure = 'opencode failed: ' . $this->trim($result['output']);
        }

        return Outcome::failed($failure);
    }

    /**
     * @param list<string> $command
     * @return array{exit: int, output: string}
     */
    private function run(array $command, string $workdirPath, AgentRunContext $context): array
    {
        $root = dirname(__DIR__, 3);
        $path = $root . '/bin/agent:' . $root . '/bin:' . (string) getenv('PATH');
        $pipe = proc_open(
            $command,
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $workdirPath,
            [
                'HOME' => (string) getenv('HOME'),
                'PATH' => $path,
                'ENS_OUTCOME_FILE' => $context->outcomePath(),
                'ENS_STAGE' => $context->stage(),
                'ENS_ROLE' => $context->role(),
                'ENS_ALLOWED_OUTCOMES' => implode(',', $context->allowedOutcomes()),
            ],
        );
        if ($pipe === false) {
            return ['exit' => 127, 'output' => 'proc_open failed'];
        }

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        $output = ($stdout === false ? '' : $stdout) . ($stderr === false ? '' : $stderr);
        $deadline = time() + $this->timeoutSeconds;
        while (proc_get_status($pipe)['running'] ?? false) {
            if (time() > $deadline) {
                proc_terminate($pipe);
                $output .= "\nexecution timed out";
                break;
            }
            usleep(1_000_000);
        }

        $exit = proc_get_status($pipe)['exitcode'] ?? 1;
        proc_close($pipe);

        return ['exit' => $exit, 'output' => $output];
    }

    private function trim(string $output): string
    {
        return trim(mb_substr($output, -4000));
    }
}
