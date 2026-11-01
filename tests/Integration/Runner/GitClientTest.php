<?php

declare(strict_types=1);

namespace App\Tests\Integration\Runner;

use App\Domain\Runner\BranchSyncStrategy;
use App\Domain\Runner\CommitMessage;
use App\Domain\Vcs\Provider;
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
        $message = new CommitMessage(
            'feat(Kaio/KAN-1): Ship the feature',
            "Agent: Kaio\nRole: Coder",
            'Kaio',
            'kaio@ensemble.local',
        );
        $published = $this->git->commitAndPush($workdir, 'agent/KAN-1', message: $message);
        self::assertTrue($published->ok);
        $log = (string) shell_exec('git -C ' . escapeshellarg($workdir) . ' log -1 --format=%an%n%s%n%b');
        self::assertStringContainsString("Kaio\nfeat(Kaio/KAN-1): Ship the feature", $log);
        self::assertStringContainsString('Agent: Kaio', $log);
        shell_exec(
            'git -C ' . escapeshellarg($this->base) . '/bare.git update-ref refs/heads/main refs/heads/agent/KAN-1 2>/dev/null'
        );
        self::assertSame(
            'agent work',
            trim((string) shell_exec('git -C ' . escapeshellarg($this->base) . '/bare.git show agent/KAN-1:feature.md')),
        );
    }

    public function testSyncsTheWorkBranchWithBaseAndKeepsWorkOnConflicts(): void
    {
        $workdir = $this->base . '/work';
        $url = 'file://' . $this->base . '/bare.git';
        $branch = 'feat/KAN-1-ship-it';

        self::assertTrue($this->git->ensureClone($url, 'main', $branch, $workdir)->ok);
        $this->configureIdentity($workdir);
        file_put_contents($workdir . '/README.md', 'work side');
        self::assertTrue($this->git->commitAndPush($workdir, $branch)->ok);

        $seed = $this->base . '/seed';
        file_put_contents($seed . '/README.md', 'base side');
        shell_exec(
            'git -C ' . escapeshellarg($seed)
            . ' add -A && git -C ' . escapeshellarg($seed)
            . ' commit -q -m base && git -C ' . escapeshellarg($seed)
            . ' push -q origin main 2>/dev/null'
        );

        $conflict = $this->git->syncWithBase($workdir, $branch, 'main', BranchSyncStrategy::Merge);
        self::assertFalse($conflict->ok);
        self::assertStringContainsString('git merge failed', $conflict->message);

        $kept = $this->git->syncWithBase($workdir, $branch, 'main', BranchSyncStrategy::KeepWork);
        self::assertTrue($kept->ok);
        self::assertSame('work side', trim((string) file_get_contents($workdir . '/README.md')));
    }

    private function configureIdentity(string $path): void
    {
        shell_exec(
            'git -C ' . escapeshellarg($path)
            . ' config user.email t@t && git -C ' . escapeshellarg($path)
            . ' config user.name t'
        );
    }

    public function testProbesALocalRepositoryAndMapsProviderTokenUsers(): void
    {
        self::assertTrue($this->git->probe('file://' . $this->base . '/bare.git', Provider::GitHub, null)->ok());
        self::assertFalse($this->git->probe('file://' . $this->base . '/missing.git', Provider::GitHub, null)->ok());

        self::assertSame('x-access-token', Provider::GitHub->gitTokenUser());
        self::assertSame('oauth2', Provider::GitLab->gitTokenUser());
        self::assertSame('x-token-auth', Provider::Bitbucket->gitTokenUser());
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
