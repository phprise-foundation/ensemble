<?php

declare(strict_types=1);

namespace App\Tests\Integration\Runner;

use App\Infrastructure\Runner\GitClient;
use PHPUnit\Framework\TestCase;

final class GitClientTest extends TestCase
{
    private GitClient $git;

    private string $base;

    public static function setUpBeforeClass(): void
    {
        if (shell_exec('command -v git') === null) {
            self::markTestSkipped('git binary unavailable');
        }
    }

    protected function setUp(): void
    {
        $this->git = new GitClient();
        $this->base = sys_get_temp_dir() . '/ensemble-git-' . uniqid();
        mkdir($this->base . '/bare.git', 0775, true);
        shell_exec('git -C ' . escapeshellarg($this->base) . '/bare.git init --bare -b main 2>/dev/null');
        $seed = $this->base . '/seed';
        shell_exec('git clone ' . escapeshellarg($this->base) . '/bare.git ' . escapeshellarg($seed) . ' 2>/dev/null');
        $this->configureIdentity($seed);
        shell_exec('echo hello > ' . escapeshellarg($seed) . '/README.md');
        shell_exec(
            'git -C ' . escapeshellarg($seed)
            . ' add -A && git -C ' . escapeshellarg($seed)
            . ' commit -q -m seed && git -C ' . escapeshellarg($seed)
            . ' push -q -u origin main 2>/dev/null'
        );
    }

    protected function tearDown(): void
    {
        shell_exec('rm -rf ' . escapeshellarg($this->base));
    }

    public function testClonesDerivesTheAgentBranchAndPushesItBack(): void
    {
        $workdir = $this->base . '/work';
        $url = 'file://' . $this->base . '/bare.git';

        $prepared = $this->git->ensureClone($url, 'main', 'agent/KAN-1', $workdir);
        self::assertTrue($prepared->ok);
        self::assertTrue(is_file($workdir . '/README.md'));
        self::assertSame('agent/KAN-1', trim((string) shell_exec('git -C ' . escapeshellarg($workdir) . ' branch --show-current')));
        $this->configureIdentity($workdir);

        file_put_contents($workdir . '/feature.md', 'agent work');
        $published = $this->git->commitAndPush($workdir, 'agent/KAN-1');
        self::assertTrue($published->ok);
        shell_exec(
            'git -C ' . escapeshellarg($this->base) . '/bare.git update-ref refs/heads/main refs/heads/agent/KAN-1 2>/dev/null'
        );
        self::assertSame(
            'agent work',
            trim((string) shell_exec('git -C ' . escapeshellarg($this->base) . '/bare.git show agent/KAN-1:feature.md')),
        );
    }

    private function configureIdentity(string $path): void
    {
        shell_exec(
            'git -C ' . escapeshellarg($path)
            . ' config user.email t@t && git -C ' . escapeshellarg($path)
            . ' config user.name t'
        );
    }

    public function testCloneRefusalBecomesAFailedOutcome(): void
    {        $prepared = $this->git->ensureClone(
            'file://' . $this->base . '/missing.git',
            'main',
            'agent/KAN-1',
            $this->base . '/other',
        );

        self::assertFalse($prepared->ok);
        self::assertStringContainsString('git clone failed', $prepared->message);
    }
}
