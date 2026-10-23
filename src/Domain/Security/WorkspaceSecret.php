<?php

declare(strict_types=1);

namespace App\Domain\Security;

use App\Domain\Exception\ValidationException;
use App\Domain\Tenancy\WorkspaceId;
use DateTimeImmutable;

final class WorkspaceSecret
{
    private SecretName $name;
    private Ciphertext $ciphertext;
    private ?DateTimeImmutable $updatedAt;

    public function __construct(
        private readonly WorkspaceSecretId $id,
        private readonly WorkspaceId $workspaceId,
        string $name,
        string $ciphertext,
        private readonly ?DateTimeImmutable $createdAt = null,
        ?DateTimeImmutable $updatedAt = null,
    ) {
        $this->name = new SecretName($name);
        $this->ciphertext = new Ciphertext($ciphertext);
        $this->updatedAt = $updatedAt;
    }

    public function id(): WorkspaceSecretId
    {
        return $this->id;
    }

    public function workspaceId(): WorkspaceId
    {
        return $this->workspaceId;
    }

    public function name(): SecretName
    {
        return $this->name;
    }

    public function ciphertext(): Ciphertext
    {
        return $this->ciphertext;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt ?? new DateTimeImmutable();
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt ?? $this->createdAt();
    }

    public function rotate(Ciphertext $ciphertext): void
    {
        $this->ciphertext = $ciphertext;
        $this->updatedAt = new DateTimeImmutable();
    }
}
