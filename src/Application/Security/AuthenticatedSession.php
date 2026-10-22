<?php

declare(strict_types=1);

namespace App\Application\Security;

use App\Domain\Tenancy\User;

final readonly class AuthenticatedSession
{
    public function __construct(private string $token, private User $user)
    {
    }

    public function token(): string
    {
        return $this->token;
    }

    public function user(): User
    {
        return $this->user;
    }
}
