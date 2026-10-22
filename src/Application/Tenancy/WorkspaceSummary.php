<?php

declare(strict_types=1);

namespace App\Application\Tenancy;

use App\Domain\Tenancy\Workspace;
use App\Domain\Tenancy\MembershipRole;

final readonly class WorkspaceSummary
{
    public function __construct(private Workspace $workspace, private ?MembershipRole $role)
    {
    }

    public function workspace(): Workspace
    {
        return $this->workspace;
    }

    public function role(): ?MembershipRole
    {
        return $this->role;
    }
}
