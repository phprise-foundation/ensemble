<?php

declare(strict_types=1);

namespace App\Domain\Vcs;

use App\Domain\Exception\ValidationException;
use App\Domain\Security\WorkspaceSecretId;
use App\Domain\Tenancy\WorkspaceId;
use DateTimeImmutable;
use Phprise\Common\ValueObject\StringObject;

final class GitRepository
{
    private StringObject $name;
    private RepositoryUrl $url;
    private BaseBranch $baseBranch;
    private ?WorkspaceSecretId $secretId;
    private ?DateTimeImmutable $updatedAt;

    public function __construct(
        private readonly GitRepositoryId $id,
        private readonly WorkspaceId $workspaceId,
        string $name,
        private readonly Provider $provider,
        string $url,
        string $baseBranch,
        ?WorkspaceSecretId $secretId = null,
        private readonly ?DateTimeImmutable $createdAt = null,
        ?DateTimeImmutable $updatedAt = null,
    ) {
        $this->name = new StringObject($this->validName($name));
        $this->url = new RepositoryUrl($url);
        $this->baseBranch = new BaseBranch($baseBranch);
        $this->secretId = $secretId;
        $this->updatedAt = $updatedAt;
    }

    public function id(): GitRepositoryId
    {
        return $this->id;
    }

    public function workspaceId(): WorkspaceId
    {
        return $this->workspaceId;
    }

    public function name(): string
    {
        return $this->name->getValue();
    }

    public function provider(): Provider
    {
        return $this->provider;
    }

    public function url(): RepositoryUrl
    {
        return $this->url;
    }

    public function baseBranch(): BaseBranch
    {
        return $this->baseBranch;
    }

    public function secretId(): ?WorkspaceSecretId
    {
        return $this->secretId;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt ?? new DateTimeImmutable();
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt ?? $this->createdAt();
    }

    public function rename(string $name): void
    {
        $this->name = new StringObject($this->validName($name));
    }

    public function changeUrl(string $url): void
    {
        $this->url = new RepositoryUrl($url);
    }

    public function changeBaseBranch(string $baseBranch): void
    {
        $this->baseBranch = new BaseBranch($baseBranch);
    }

    public function useSecret(?WorkspaceSecretId $secretId): void
    {
        $this->secretId = $secretId;
    }

    public function touch(DateTimeImmutable $at): void
    {
        $this->updatedAt = $at;
    }

    private function validName(string $name): string
    {
        $normalized = trim($name);
        if ($normalized === '' || mb_strlen($normalized) > 80) {
            throw new ValidationException('name', 'Repository name must have 1 to 80 characters.');
        }

        return $normalized;
    }
}
