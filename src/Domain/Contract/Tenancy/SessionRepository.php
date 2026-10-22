<?php

declare(strict_types=1);

namespace App\Domain\Contract\Tenancy;

use App\Domain\Security\Session;
use App\Domain\Security\SessionTokenHash;
use DateTimeImmutable;

interface SessionRepository
{
    public function add(Session $session): void;

    public function find(SessionTokenHash $tokenHash): ?Session;

    public function delete(SessionTokenHash $tokenHash): void;

    public function deleteExpired(DateTimeImmutable $now): void;
}
