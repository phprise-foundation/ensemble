<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'sessions')]
#[ORM\Index(name: 'idx_sessions_user', columns: ['user_id'])]
#[ORM\Index(name: 'idx_sessions_expires', columns: ['expires_at'])]
final class SessionRecord
{
    #[ORM\Id]
    #[ORM\Column(name: 'token_hash', type: 'string', length: 64)]
    private string $tokenHash;

    #[ORM\Column(name: 'user_id', type: 'string', length: 32)]
    private string $userId;

    #[ORM\Column(name: 'expires_at', type: 'datetime_immutable')]
    private DateTimeImmutable $expiresAt;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    public function __construct(
        string $tokenHash,
        string $userId,
        DateTimeImmutable $expiresAt,
        DateTimeImmutable $createdAt,
    ) {
        $this->tokenHash = $tokenHash;
        $this->userId = $userId;
        $this->expiresAt = $expiresAt;
        $this->createdAt = $createdAt;
    }

    public function tokenHash(): string
    {
        return $this->tokenHash;
    }

    public function userId(): string
    {
        return $this->userId;
    }

    public function expiresAt(): DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
