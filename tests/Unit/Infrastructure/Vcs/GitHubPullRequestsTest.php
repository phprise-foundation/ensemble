<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Vcs;

use App\Domain\Contract\Security\SecretCipher;
use App\Domain\Contract\Security\WorkspaceSecretRepository;
use App\Domain\Contract\Vcs\GitRepositories;
use App\Domain\Kanban\Issue;
use App\Domain\Kanban\IssueId;
use App\Domain\Kanban\IssueKey;
use App\Domain\Kanban\IssueTypeId;
use App\Domain\Kanban\StatusId;
use App\Domain\Kanban\Workdir;
use App\Domain\Kanban\WorkdirId;
use App\Domain\Kanban\WorkdirKind;
use App\Domain\Kanban\WorkdirState;
use App\Domain\Security\WorkspaceSecret;
use App\Domain\Security\WorkspaceSecretId;
use App\Domain\Tenancy\WorkspaceId;
use App\Domain\Vcs\BaseBranch;
use App\Domain\Vcs\GitHubSlug;
use App\Domain\Vcs\GitRepository;
use App\Domain\Vcs\GitRepositoryId;
use App\Domain\Vcs\Provider;
use App\Domain\Vcs\RepositoryUrl;
use App\Infrastructure\Vcs\GitHubHttp;
use App\Infrastructure\Vcs\GitHubPullRequests;
use App\Infrastructure\Vcs\GitHubResponse;
use Override;
use PHPUnit\Framework\TestCase;

final class GitHubPullRequestsTest extends TestCase
{
    private WorkspaceId $workspaceId;
    private GitRepositoryId $repositoryId;
    private FakeGitRepositories $repositories;
    private FakeGitHubHttp $http;
    private Workdir $workdir;
    private Issue $issue;

    protected function setUp(): void
    {
        $this->workspaceId = new WorkspaceId(bin2hex(random_bytes(16)));
        $this->repositoryId = GitRepositoryId::generate();
        $this->repositories = new FakeGitRepositories();
        $this->http = new FakeGitHubHttp();
        $this->workdir = new Workdir(
            WorkdirId::generate(),
            $this->workspaceId,
            IssueId::generate(),
            WorkdirKind::Repository,
            $this->repositoryId,
            'agent/APP-1',
            WorkdirState::Bound,
        );
        $this->issue = new Issue(
            IssueId::generate(),
            new IssueKey('APP-1'),
            $this->workspaceId,
            IssueTypeId::generate(),
            StatusId::generate(),
            'Ship the runner',
            'Wire the automatic pull request.',
            'admin',
        );
    }

    public function testCreatesAPullRequestWhenNoneExists(): void
    {
        $this->repositories->repository = $this->repository();
        $this->http->responses = [
            new GitHubResponse(200, '[]'),
            new GitHubResponse(201, '{"html_url":"https://github.com/acme/app/pull/7"}'),
        ];

        $result = $this->client()->open($this->workdir, $this->issue);

        self::assertFalse($result->isFailed());
        self::assertSame('https://github.com/acme/app/pull/7', $result->url());
        self::assertCount(2, $this->http->requests);
        self::assertSame('GET', $this->http->requests[0]['method']);
        self::assertStringContainsString('head=acme%3Aagent%2FAPP-1', $this->http->requests[0]['url']);
        self::assertSame('secret-token', $this->http->requests[0]['token']);
        self::assertSame('POST', $this->http->requests[1]['method']);
        self::assertSame('agent/APP-1', $this->http->requests[1]['payload']['head']);
        self::assertSame('main', $this->http->requests[1]['payload']['base']);
        self::assertSame('APP-1: Ship the runner', $this->http->requests[1]['payload']['title']);
        self::assertSame('Wire the automatic pull request.', $this->http->requests[1]['payload']['body']);
    }

    public function testReusesAnExistingPullRequest(): void
    {
        $this->repositories->repository = $this->repository();
        $this->http->responses = [
            new GitHubResponse(200, '[{"html_url":"https://github.com/acme/app/pull/3"}]'),
        ];

        $result = $this->client()->open($this->workdir, $this->issue);

        self::assertSame('https://github.com/acme/app/pull/3', $result->url());
        self::assertCount(1, $this->http->requests);
    }

    public function testSkipsProvidersOtherThanGitHub(): void
    {
        $this->repositories->repository = $this->repository(Provider::GitLab);

        $result = $this->client()->open($this->workdir, $this->issue);

        self::assertFalse($result->isFailed());
        self::assertNull($result->url());
        self::assertStringContainsString('only implemented for GitHub', $result->message());
        self::assertCount(0, $this->http->requests);
    }

    public function testFailsWhenGitHubRejectsThePullRequest(): void
    {
        $this->repositories->repository = $this->repository();
        $this->http->responses = [
            new GitHubResponse(200, '[]'),
            new GitHubResponse(422, '{"message":"Validation Failed"}'),
        ];

        $result = $this->client()->open($this->workdir, $this->issue);

        self::assertTrue($result->isFailed());
        self::assertStringContainsString('Validation Failed', $result->message());
    }

    public function testParsesGitHubSlugs(): void
    {
        self::assertSame('acme/app', GitHubSlug::fromUrl(new RepositoryUrl('https://github.com/acme/app.git'))->path());
        self::assertSame('acme/app', GitHubSlug::fromUrl(new RepositoryUrl('https://github.com/acme/app'))->path());
    }

    private function client(): GitHubPullRequests
    {
        return new GitHubPullRequests(
            $this->repositories,
            new FakeWorkspaceSecrets($this->workspaceId),
            new FakeSecretCipher(),
            $this->http,
        );
    }

    private function repository(Provider $provider = Provider::GitHub): GitRepository
    {
        return new GitRepository(
            $this->repositoryId,
            $this->workspaceId,
            'App',
            $provider,
            'https://github.com/acme/app.git',
            'main',
            new WorkspaceSecretId(bin2hex(random_bytes(16))),
        );
    }
}

final class FakeGitRepositories implements GitRepositories
{
    public ?GitRepository $repository = null;

    #[Override]
    public function add(GitRepository $repository): void
    {
    }

    #[Override]
    public function update(GitRepository $repository): void
    {
    }

    #[Override]
    public function delete(GitRepository $repository): void
    {
    }

    #[Override]
    public function find(WorkspaceId $workspaceId, GitRepositoryId $id): ?GitRepository
    {
        return $this->repository;
    }

    #[Override]
    public function findByName(WorkspaceId $workspaceId, string $name): ?GitRepository
    {
        return null;
    }

    #[Override]
    public function all(WorkspaceId $workspaceId): array
    {
        return [];
    }

    #[Override]
    public function referenced(\App\Domain\Security\WorkspaceSecretId $secretId): bool
    {
        return false;
    }
}

final class FakeWorkspaceSecrets implements WorkspaceSecretRepository
{
    public function __construct(private readonly WorkspaceId $workspaceId)
    {
    }

    #[Override]
    public function add(WorkspaceSecret $secret): void
    {
    }

    #[Override]
    public function update(WorkspaceSecret $secret): void
    {
    }

    #[Override]
    public function delete(WorkspaceSecret $secret): void
    {
    }

    #[Override]
    public function find(WorkspaceId $workspaceId, WorkspaceSecretId $id): ?WorkspaceSecret
    {
        return new WorkspaceSecret($id, $this->workspaceId, 'github', base64_encode('cipher'));
    }

    #[Override]
    public function findByName(WorkspaceId $workspaceId, string $name): ?WorkspaceSecret
    {
        return null;
    }

    #[Override]
    public function all(WorkspaceId $workspaceId): array
    {
        return [];
    }
}

final class FakeSecretCipher implements SecretCipher
{
    #[Override]
    public function encrypt(string $plaintext): string
    {
        return base64_encode($plaintext);
    }

    #[Override]
    public function decrypt(string $ciphertext): string
    {
        return 'secret-token';
    }
}

final class FakeGitHubHttp implements GitHubHttp
{
    /** @var list<array{method: string, url: string, token: ?string, payload: ?array<string, mixed>}> */
    public array $requests = [];

    /** @var list<GitHubResponse> */
    public array $responses = [];

    #[Override]
    public function request(string $method, string $url, ?string $token, ?array $payload = null): GitHubResponse
    {
        $this->requests[] = ['method' => $method, 'url' => $url, 'token' => $token, 'payload' => $payload];

        return array_shift($this->responses) ?? new GitHubResponse(500, 'no response');
    }
}
