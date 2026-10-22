<?php

declare(strict_types=1);

namespace App\Application\Security;

use App\Domain\Contract\Tenancy\SessionRepository;
use App\Domain\Contract\Tenancy\UserRepository;
use App\Domain\Exception\AuthenticationFailedException;
use App\Domain\Security\SessionTokenHash;
use App\Domain\Tenancy\User;
use DateTimeImmutable;

final readonly class ReadCurrentUser
{
    public function __construct(
        private SessionRepository $sessions,
        private UserRepository $users,
    ) {
    }

    public function execute(?string $token): User
    {
        if ($token === null || $token === '') {
            throw AuthenticationFailedException::missingToken();
        }

        $hash = SessionTokenHash::fromToken($token);
        $session = $this->sessions->find($hash);
        if ($session === null || !$session->isActive(new DateTimeImmutable())) {
            $this->sessions->delete($hash);
            throw AuthenticationFailedException::missingToken();
        }

        return $this->users->find($session->userId()) ?? throw AuthenticationFailedException::missingToken();
    }
}
