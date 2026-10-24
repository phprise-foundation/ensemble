<?php

declare(strict_types=1);

namespace App\Infrastructure\Runner;

use App\Domain\Runner\Outcome;
use App\Domain\Runner\ResolutionStrategy;

final class GitClient
{
    private const DEFAULT_TIMEOUT_SECONDS = 900;

    public function __construct(
        private int $timeoutSeconds = self::DEFAULT_TIMEOUT_SECONDS,
    ) {
    }

    /** Ensures `$path` is a git working copy of `$branch` derived from `$base`. */
    public function ensureClone(
        string $url,
        string $base,
        string $branch,
        string $path,
        ?string $token = null,
    ): Outcome {
        if (!is_dir($path . '/.git')) {
            $parent = dirname($path);
            if (!is_dir($parent)) {
                @mkdir($parent, 0775, true);
            }

            $clone = $this->git(
                is_dir($parent) ? $parent : (string) getenv('HOME'),
                $this->auth(['clone', '--no-checkout', '--branch', $base, $url, $path], $token),
            );
            if ($clone['exit'] !== 0) {
                return Outcome::failed('git clone failed: ' . $this->trailing($clone['output']));
            }
        }

        $fetch = $this->git($path, $this->auth(['fetch', 'origin'], $token));
        if ($fetch['exit'] !== 0) {
            return Outcome::failed('git fetch failed: ' . $this->trailing($fetch['output']));
        }

        $checkout = $this->git($path, $this->auth(['checkout', '-B', $branch, 'origin/' . $base], $token));
        if ($checkout['exit'] !== 0) {
            return Outcome::failed('git checkout failed: ' . $this->trailing($checkout['output']));
        }

        return Outcome::succeeded($branch);
    }

    /** Commits whatever the workdir changed and pushes the branch. */
    public function commitAndPush(string $path, string $branch, ?string $token = null): Outcome
    {
        $committed = $this->commitAll($path, 'run: apply agent work on ' . $branch);
        if ($committed !== null) {
            return $committed;
        }

        return $this->push($path, $branch, false, $token);
    }

    /** Reconciles a conflicting workdir, leaving the workdir ready to run again. */
    public function reconcile(
        string $path,
        string $branch,
        string $base,
        ResolutionStrategy $strategy,
        ?string $token,
    ): Outcome {
        $fetch = $this->git($path, $this->auth(['fetch', 'origin'], $token));
        if ($fetch['exit'] !== 0) {
            return Outcome::failed('git fetch failed: ' . $this->trailing($fetch['output']));
        }

        return match ($strategy) {
            ResolutionStrategy::RemoteWins => $this->remoteWins($path, $branch, $base, $token),
            ResolutionStrategy::LocalWins => $this->localWins($path, $branch, $token),
            ResolutionStrategy::Merge => $this->mergeAndPush($path, $branch, $base, $token),
        };
    }

    private function remoteWins(string $path, string $branch, string $base, ?string $token): Outcome
    {
        $reset = $this->git($path, $this->auth(['checkout', '-B', $branch, 'origin/' . $base], $token));
        if ($reset['exit'] !== 0) {
            return Outcome::failed('git checkout failed: ' . $this->trailing($reset['output']));
        }

        return $this->push($path, $branch, true, $token);
    }

    private function localWins(string $path, string $branch, ?string $token): Outcome
    {
        $committed = $this->commitAll($path, 'run: keep the local work on ' . $branch);
        if ($committed !== null) {
            return $committed;
        }

        return $this->push($path, $branch, true, $token);
    }

    private function mergeAndPush(string $path, string $branch, string $base, ?string $token): Outcome
    {
        $committed = $this->commitAll($path, 'run: keep the local work on ' . $branch);
        if ($committed !== null) {
            return $committed;
        }

        $merge = $this->git($path, $this->auth(['merge', 'origin/' . $base], $token));
        if ($merge['exit'] !== 0) {
            return Outcome::failed('git merge failed: ' . $this->trailing($merge['output']));
        }

        return $this->push($path, $branch, false, $token);
    }

    private function commitAll(string $path, string $message): ?Outcome
    {
        $add = $this->git($path, ['add', '-A']);
        if ($add['exit'] !== 0) {
            return Outcome::failed('git add failed: ' . $this->trailing($add['output']));
        }

        $status = $this->git($path, ['status', '--porcelain']);
        if ($status['output'] === '') {
            return null;
        }

        $commit = $this->git($path, ['commit', '-q', '-m', $message]);
        if ($commit['exit'] !== 0) {
            return Outcome::failed('git commit failed: ' . $this->trailing($commit['output']));
        }

        return null;
    }

    private function push(string $path, string $branch, bool $force, ?string $token): Outcome
    {
        $arguments = ['push', '-u', 'origin', $branch];
        if ($force) {
            array_unshift($arguments, '--force');
        }

        $push = $this->git($path, $this->auth($arguments, $token));
        if ($push['exit'] !== 0) {
            return Outcome::failed('git push failed: ' . $this->trailing($push['output']));
        }

        return Outcome::succeeded('pushed ' . $branch);
    }

    /**
     * @param list<string> $arguments
     * @return array{exit: int, output: string}
     */
    private function git(string $path, array $arguments): array
    {
        $pipe = proc_open(
            ['git', ...$arguments],            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $path,
            ['GIT_TERMINAL_PROMPT' => '0', 'HOME' => (string) getenv('HOME'), 'PATH' => (string) getenv('PATH')],
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
                $output .= "\ngit timed out";
                break;
            }
            usleep(1_000_000);
        }

        $exit = proc_get_status($pipe)['exitcode'] ?? 1;
        proc_close($pipe);

        return ['exit' => $exit, 'output' => $output];
    }

    /**
     * @param list<string> $arguments
     * @return list<string>
     */
    private function auth(array $arguments, ?string $token): array
    {
        if ($token === null) {
            return $arguments;
        }

        $header = 'Authorization: Basic ' . base64_encode('x-access-token:' . $token);

        return ['-c', 'http.extraHeader=' . $header, ...$arguments];
    }

    private function trailing(string $output): string
    {
        return trim(mb_substr($output, -2000));
    }
}
