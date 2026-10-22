<?php

declare(strict_types=1);

namespace App\Application\Tenancy;

use App\Domain\Contract\Tenancy\PasswordHasher;
use App\Domain\Contract\Tenancy\UserRepository;
use App\Domain\Exception\ConflictException;
use App\Domain\Security\PlainPassword;
use App\Domain\Tenancy\Login;
use App\Domain\Tenancy\User;
use App\Domain\Tenancy\UserId;

final readonly class RegisterUser
{
    public function __construct(private UserRepository $users, private PasswordHasher $hasher)
    {
    }

    public function execute(Login $login, PlainPassword $password, string $displayName, bool $admin): User
    {
        if ($this->users->findByLogin($login) !== null) {
            throw ConflictException::duplicateLogin();
        }

        $user = new User(
            UserId::generate(),
            $login,
            $this->hasher->hash($password),
            $displayName,
            null,
            '#333333',
            $admin,
        );
        $this->users->add($user);

        return $user;
    }
}
