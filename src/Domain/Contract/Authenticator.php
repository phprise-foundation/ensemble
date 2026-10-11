<?php

declare(strict_types=1);

namespace App\Domain\Contract;

interface Authenticator
{
    public function isValid(?string $token): bool;
}
