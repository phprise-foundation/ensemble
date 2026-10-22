<?php

declare(strict_types=1);

namespace App\Domain\Security;

use App\Domain\Tenancy\UserId;
use DateTimeImmutable;

final readonly class Session
{
    public function __construct(
        private SessionTokenHash $tokenHash,
        private UserId $userId,
        private DateTimeImmutable $expiresAt,
        private DateTimeImmutable $createdAt,
    ) {
    }

    public function tokenHash(): SessionTokenHash
    {
        return $this->tokenHash;
    }

    public function userId(): UserId
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

    public function isActive(DateTimeImmutable $now): bool
    {
        return $this->expiresAt > $now;
    }
}
