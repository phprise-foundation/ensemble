<?php

declare(strict_types=1);

namespace App\Application\Tenancy;

use App\Domain\Tenancy\MembershipRole;
use App\Domain\Tenancy\User;

final readonly class MemberSummary
{
    public function __construct(private User $user, private MembershipRole $role)
    {
    }

    public function user(): User
    {
        return $this->user;
    }

    public function role(): MembershipRole
    {
        return $this->role;
    }
}
