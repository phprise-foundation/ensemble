<?php

declare(strict_types=1);

namespace App\Application\Security;

use App\Domain\Contract\Tenancy\PasswordHasher;
use App\Domain\Contract\Tenancy\SessionRepository;
use App\Domain\Contract\Tenancy\TokenGenerator;
use App\Domain\Contract\Tenancy\UserRepository;
use App\Domain\Exception\AuthenticationFailedException;
use App\Domain\Security\PlainPassword;
use App\Domain\Security\Session;
use App\Domain\Security\SessionTokenHash;
use App\Domain\Tenancy\Login;
use DateTimeImmutable;

final readonly class AuthenticateUser
{
    public function __construct(
        private UserRepository $users,
        private SessionRepository $sessions,
        private PasswordHasher $hasher,
        private TokenGenerator $tokens,
        private int $sessionDays = 30,
    ) {
    }

    public function execute(Login $login, PlainPassword $password): AuthenticatedSession
    {
        $user = $this->users->findByLogin($login);
        if ($user === null || !$this->hasher->verify($password, $user->passwordHash())) {
            throw AuthenticationFailedException::invalidCredentials();
        }

        $now = new DateTimeImmutable();
        $this->sessions->deleteExpired($now);
        $token = $this->tokens->generate();
        $session = new Session(
            SessionTokenHash::fromToken($token),
            $user->id(),
            new DateTimeImmutable(sprintf('+%d days', $this->sessionDays)),
            $now,
        );
        $this->sessions->add($session);

        return new AuthenticatedSession($token, $user);
    }
}
