<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'workspace_secrets')]
#[ORM\UniqueConstraint(name: 'uniq_workspace_secrets_name', columns: ['workspace_id', 'name'])]
#[ORM\Index(name: 'idx_workspace_secrets_workspace', columns: ['workspace_id'])]
final class WorkspaceSecretRecord
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 32)]
    private string $id;

    #[ORM\Column(name: 'workspace_id', type: 'string', length: 32)]
    private string $workspaceId;

    #[ORM\Column(type: 'string', length: 80)]
    private string $name;

    #[ORM\Column(type: 'text')]
    private string $ciphertext;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private DateTimeImmutable $updatedAt;

    public function __construct(
        string $id,
        string $workspaceId,
        string $name,
        string $ciphertext,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt,
    ) {
        $this->id = $id;
        $this->workspaceId = $workspaceId;
        $this->name = $name;
        $this->ciphertext = $ciphertext;
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

    public function ciphertext(): string
    {
        return $this->ciphertext;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function sync(string $name, string $ciphertext, DateTimeImmutable $updatedAt): void
    {
        $this->name = $name;
        $this->ciphertext = $ciphertext;
        $this->updatedAt = $updatedAt;
    }
}
