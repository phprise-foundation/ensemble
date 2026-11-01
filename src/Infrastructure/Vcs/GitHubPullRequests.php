<?php

declare(strict_types=1);

namespace App\Infrastructure\Vcs;

use App\Domain\Contract\Security\SecretCipher;
use App\Domain\Contract\Security\WorkspaceSecretRepository;
use App\Domain\Contract\Vcs\GitRepositories;
use App\Domain\Exception\ValidationException;
use App\Domain\Kanban\Issue;
use App\Domain\Kanban\Workdir;
use App\Domain\Security\WorkspaceSecretId;
use App\Domain\Vcs\GitHubSlug;
use App\Domain\Vcs\MergeResult;
use App\Domain\Vcs\Provider;
use App\Domain\Vcs\PullRequestResult;
use App\Domain\Vcs\PullRequests;
use Override;

final readonly class GitHubPullRequests implements PullRequests
{
    public function __construct(
        private GitRepositories $repositories,
        private WorkspaceSecretRepository $secrets,
        private SecretCipher $cipher,
        private GitHubHttp $http,
        private string $apiBase = 'https://api.github.com',
    ) {
    }

    #[Override]
    public function open(Workdir $workdir, Issue $issue): PullRequestResult
    {
        $repositoryId = $workdir->repositoryId();
        if ($repositoryId === null) {
            return PullRequestResult::skipped('the workdir has no repository');
        }

        $repository = $this->repositories->find($workdir->workspaceId(), $repositoryId);
        if ($repository === null) {
            return PullRequestResult::failed('the workdir repository is no longer registered');
        }

        if ($repository->provider() !== Provider::GitHub) {
            return PullRequestResult::skipped('pull requests are only implemented for GitHub');
        }

        $branch = $workdir->branch()?->value();
        if ($branch === null) {
            return PullRequestResult::failed('the workdir has no branch to propose');
        }

        try {
            $slug = GitHubSlug::fromUrl($repository->url());
        } catch (ValidationException $invalid) {
            return PullRequestResult::failed($invalid->getMessage());
        }

        $token = $this->token($repository->workspaceId(), $repository->secretId());
        $pullsUrl = $this->apiBase . '/repos/' . $slug->path() . '/pulls';
        $listed = $this->http->request(
            'GET',
            $pullsUrl . '?' . http_build_query(['head' => $slug->owner() . ':' . $branch, 'state' => 'open']),
            $token,
        );
        if ($listed->status() !== 200) {
            return PullRequestResult::failed(
                'GitHub could not list pull requests (' . $listed->status() . '): ' . $this->reason($listed->body()),
            );
        }

        /** @var mixed $existing */
        $existing = json_decode($listed->body(), true);
        if (is_array($existing) && isset($existing[0]) && is_array($existing[0]) && isset($existing[0]['html_url']) && is_string($existing[0]['html_url'])) {
            return PullRequestResult::opened($existing[0]['html_url']);
        }

        $created = $this->http->request('POST', $pullsUrl, $token, [
            'title' => $this->title($issue),
            'head' => $branch,
            'base' => $repository->baseBranch()->value(),
            'body' => $this->body($issue),
        ]);
        if ($created->status() !== 201) {
            return PullRequestResult::failed(
                'GitHub rejected the pull request (' . $created->status() . '): ' . $this->reason($created->body()),
            );
        }

        /** @var mixed $pullRequest */
        $pullRequest = json_decode($created->body(), true);
        $url = '';
        if (is_array($pullRequest) && isset($pullRequest['html_url']) && is_string($pullRequest['html_url'])) {
            $url = $pullRequest['html_url'];
        }

        return $url === ''
            ? PullRequestResult::failed('GitHub created the pull request without returning its URL')
            : PullRequestResult::opened($url);
    }

    #[Override]
    public function merge(Workdir $workdir, int $number, string $method): MergeResult
    {
        if ($number < 1) {
            return MergeResult::failed('the pull request number is invalid');
        }
        if (! in_array($method, ['merge', 'squash', 'rebase'], true)) {
            return MergeResult::failed('the merge method must be merge, squash or rebase');
        }

        $repositoryId = $workdir->repositoryId();
        if ($repositoryId === null) {
            return MergeResult::failed('the workdir has no repository');
        }

        $repository = $this->repositories->find($workdir->workspaceId(), $repositoryId);
        if ($repository === null) {
            return MergeResult::failed('the workdir repository is no longer registered');
        }
        if ($repository->provider() !== Provider::GitHub) {
            return MergeResult::failed('pull request merges are only implemented for GitHub');
        }

        try {
            $slug = GitHubSlug::fromUrl($repository->url());
        } catch (ValidationException $invalid) {
            return MergeResult::failed($invalid->getMessage());
        }

        $token = $this->token($repository->workspaceId(), $repository->secretId());
        $response = $this->http->request(
            'PUT',
            $this->apiBase . '/repos/' . $slug->path() . '/pulls/' . $number . '/merge',
            $token,
            ['merge_method' => $method],
        );
        if ($response->status() !== 200) {
            return MergeResult::failed(
                'GitHub rejected the merge (' . $response->status() . '): ' . $this->reason($response->body()),
            );
        }

        /** @var mixed $merged */
        $merged = json_decode($response->body(), true);
        $sha = null;
        $message = 'pull request merged';
        if (is_array($merged)) {
            if (isset($merged['sha']) && is_string($merged['sha'])) {
                $sha = $merged['sha'];
            }
            if (isset($merged['message']) && is_string($merged['message'])) {
                $message = $merged['message'];
            }
        }

        return MergeResult::merged($message, $sha);
    }

    private function token(\App\Domain\Tenancy\WorkspaceId $workspaceId, ?WorkspaceSecretId $secretId): ?string
    {
        if ($secretId === null) {
            return null;
        }

        $secret = $this->secrets->find($workspaceId, $secretId);

        return $secret === null ? null : $this->cipher->decrypt($secret->ciphertext()->value());
    }

    private function title(Issue $issue): string
    {
        return mb_substr($issue->key()->value() . ': ' . $issue->summary(), 0, 240);
    }

    private function body(Issue $issue): string
    {
        $body = trim($issue->description());

        return mb_substr($body === '' ? 'Automated delivery from Ensemble.' : $body, 0, 60000);
    }

    private function reason(string $body): string
    {
        /** @var mixed $decoded */
        $decoded = json_decode($body, true);
        if (is_array($decoded) && isset($decoded['message']) && is_string($decoded['message'])) {
            return $decoded['message'];
        }

        return mb_substr(trim($body), 0, 300);
    }
}
