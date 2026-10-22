<?php

declare(strict_types=1);

namespace App\Domain\Contract\Tenancy;

use App\Domain\Security\PasswordHash;
use App\Domain\Security\PlainPassword;

interface PasswordHasher
{
    public function hash(PlainPassword $password): PasswordHash;

    public function verify(PlainPassword $password, PasswordHash $hash): bool;
}
