<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'repositories')]
#[ORM\UniqueConstraint(name: 'uniq_repositories_name', columns: ['workspace_id', 'name'])]
#[ORM\Index(name: 'idx_repositories_workspace', columns: ['workspace_id'])]
#[ORM\Index(name: 'idx_repositories_secret', columns: ['secret_id'])]
final class GitRepositoryRecord
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 32)]
    private string $id;

    #[ORM\Column(name: 'workspace_id', type: 'string', length: 32)]
    private string $workspaceId;

    #[ORM\Column(type: 'string', length: 80)]
    private string $name;

    #[ORM\Column(type: 'string', length: 16)]
    private string $provider;

    #[ORM\Column(type: 'string', length: 2048)]
    private string $url;

    #[ORM\Column(name: 'base_branch', type: 'string', length: 100)]
    private string $baseBranch;

    #[ORM\Column(name: 'secret_id', type: 'string', length: 32, nullable: true)]
    private ?string $secretId;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private DateTimeImmutable $updatedAt;

    public function __construct(
        string $id,
        string $workspaceId,
        string $name,
        string $provider,
        string $url,
        string $baseBranch,
        ?string $secretId,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt,
    ) {
        $this->id = $id;
        $this->workspaceId = $workspaceId;
        $this->name = $name;
        $this->provider = $provider;
        $this->url = $url;
        $this->baseBranch = $baseBranch;
        $this->secretId = $secretId;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function workspaceId(): string
    {
        return $this->workspaceId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function provider(): string
    {
        return $this->provider;
    }

    public function url(): string
    {
        return $this->url;
    }

    public function baseBranch(): string
    {
        return $this->baseBranch;
    }

    public function secretId(): ?string
    {
        return $this->secretId;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function sync(
        string $name,
        string $url,
        string $baseBranch,
        ?string $secretId,
        DateTimeImmutable $updatedAt,
    ): void {
        $this->name = $name;
        $this->url = $url;
        $this->baseBranch = $baseBranch;
        $this->secretId = $secretId;
        $this->updatedAt = $updatedAt;
    }
}
