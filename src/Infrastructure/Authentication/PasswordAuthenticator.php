<?php

declare(strict_types=1);

namespace App\Infrastructure\Authentication;

use App\Domain\Contract\Authenticator;
use Override;

final readonly class PasswordAuthenticator implements Authenticator
{
    public function __construct(private string $password)
    {
    }

    #[Override]
    public function isValid(?string $token): bool
    {
        return $token !== null && hash_equals($this->password, $token);
    }
}
