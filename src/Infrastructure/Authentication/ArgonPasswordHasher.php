<?php

declare(strict_types=1);

namespace App\Infrastructure\Authentication;

use App\Domain\Contract\Tenancy\PasswordHasher;
use App\Domain\Security\PasswordHash;
use App\Domain\Security\PlainPassword;
use Override;

final readonly class ArgonPasswordHasher implements PasswordHasher
{
    #[Override]
    public function hash(PlainPassword $password): PasswordHash
    {
        return new PasswordHash(password_hash($password->value(), PASSWORD_ARGON2ID));
    }

    #[Override]
    public function verify(PlainPassword $password, PasswordHash $hash): bool
    {
        return password_verify($password->value(), $hash->value());
    }
}
