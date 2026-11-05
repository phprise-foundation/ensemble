<?php

declare(strict_types=1);

namespace App\Infrastructure\Runner;

use App\Domain\Model\InternalModel;
use App\Domain\Model\ModelCatalog;
use App\Domain\Runner\AgentExecutor;
use App\Domain\Runner\AgentRunContext;
use App\Domain\Runner\Outcome;
use App\Domain\Runner\UsageReport;
use App\Domain\Team\Agent;
use Override;

final readonly class OpenWardenExecutor implements AgentExecutor
{
    private const DEFAULT_TIMEOUT_SECONDS = 3600;
    private const CREDENTIAL_KEYS = [
        'DEEPSEEK_API_KEY',
        'SILICONFLOW_API_KEY',
        'OPENROUTER_API_KEY',
        'ANTHROPIC_API_KEY',
        'OPENAI_API_KEY',
        'GOOGLE_API_KEY',
        'GEMINI_API_KEY',
        'OPENWARDEN_CONFIG_DIR',
    ];

    public function __construct(
        private ModelCatalog $catalog,
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
        $internal = $this->catalog->resolve($agent->model())->internal();
        $result = $this->run($internal, $workdirPath, $prompt, $context);
        if ($result['exit'] === 0) {
            return Outcome::succeeded($this->trim($result['output']), $result['usage']);
        }

        return Outcome::failed('open-warden failed: ' . $this->trim($result['output']), $result['usage']);
    }

    /**
     * @return array{exit: int, output: string, usage: ?UsageReport}
     */
    private function run(InternalModel $internal, string $workdirPath, string $prompt, AgentRunContext $context): array
    {
        $promptFile = $this->writePrompt($workdirPath, $prompt);
        $reportFile = (string) tempnam(sys_get_temp_dir(), 'ensemble-report-');
        $root = dirname(__DIR__, 3);
        $path = $root . '/bin/agent:' . $root . '/bin:' . (string) getenv('PATH');
        $pipe = proc_open(
            $this->command($internal, $workdirPath, $promptFile, $reportFile),
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $workdirPath,
            $this->environment($path, $context),
        );
        if ($pipe === false) {
            unlink($promptFile);

            return ['exit' => 127, 'output' => 'proc_open failed', 'usage' => null];
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
        unlink($promptFile);
        $usage = (new UsageReportParser())->parse($reportFile);
        @unlink($reportFile);

        return ['exit' => $exit, 'output' => $output, 'usage' => $usage];
    }

    /**
     * @return list<string>
     */
    private function command(InternalModel $internal, string $workdirPath, string $promptFile, string $reportFile): array
    {
        $command = [
            'open-warden',
            'run',
            '--model',
            $internal->model(),
            '--economy',
            'balanced',
            '--workdir',
            $workdirPath,
            '--prompt-file',
            $promptFile,
            '--report-file',
            $reportFile,
        ];
        foreach ([
            '--fallback' => $internal->fallback(),
            '--subagents-model' => $internal->subagent(),
            '--subagents-fallback' => $internal->fallbackSubagent(),
        ] as $flag => $value) {
            if ($value !== '') {
                $command[] = $flag;
                $command[] = $value;
            }
        }

        return $command;
    }

    private function writePrompt(string $workdirPath, string $prompt): string
    {
        $file = (string) tempnam(sys_get_temp_dir(), 'ensemble-prompt-');
        file_put_contents($file, $prompt);

        return $file;
    }

    /**
     * @return array<string, string>
     */
    private function environment(string $path, AgentRunContext $context): array
    {
        $environment = [
            'HOME' => (string) getenv('HOME'),
            'PATH' => $path,
            'ENS_OUTCOME_FILE' => $context->outcomePath(),
            'ENS_STAGE' => $context->stage(),
            'ENS_ROLE' => $context->role(),
            'ENS_ALLOWED_OUTCOMES' => implode(',', $context->allowedOutcomes()),
            'ENS_COMMENT_FILE' => $context->commentPath(),
            'ENS_DESCRIPTION_FILE' => $context->descriptionPath(),
        ];
        foreach (self::CREDENTIAL_KEYS as $key) {
            $value = getenv($key);
            if ($value !== false && $value !== '') {
                $environment[$key] = $value;
            }
        }

        return $environment;
    }

    private function trim(string $output): string
    {
        return trim(mb_substr($output, -4000));
    }
}
