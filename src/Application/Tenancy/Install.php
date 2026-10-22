<?php

declare(strict_types=1);

namespace App\Application\Tenancy;

use App\Domain\Contract\Tenancy\MembershipRepository;
use App\Domain\Contract\Tenancy\PasswordHasher;
use App\Domain\Contract\Tenancy\UserRepository;
use App\Domain\Contract\Tenancy\WorkspaceRepository;
use App\Domain\Security\PlainPassword;
use App\Domain\Tenancy\Login;
use App\Domain\Tenancy\Membership;
use App\Domain\Tenancy\MembershipRole;
use App\Domain\Tenancy\User;
use App\Domain\Tenancy\UserId;
use App\Domain\Tenancy\Workspace;
use App\Domain\Tenancy\WorkspaceId;
use App\Domain\Tenancy\WorkspacePrefix;
use RuntimeException;

final readonly class Install
{
    public function __construct(
        private UserRepository $users,
        private WorkspaceRepository $workspaces,
        private MembershipRepository $memberships,
        private PasswordHasher $hasher,
    ) {
    }

    public function execute(
        Login $login,
        PlainPassword $password,
        string $workspaceName,
        WorkspacePrefix $prefix,
    ): void {
        if (!$this->users->existsAny()) {
            $this->users->add(new User(
                UserId::generate(),
                $login,
                $this->hasher->hash($password),
                $login->value(),
                null,
                '#3b82f6',
                true,
            ));
        }

        if ($this->workspaces->existsAny()) {
            return;
        }

        $owner = $this->users->findByLogin($login) ?? ($this->users->all()[0] ?? throw new RuntimeException('No user available to own the workspace.'));
        $workspace = new Workspace(WorkspaceId::generate(), $workspaceName, $prefix);
        $this->workspaces->add($workspace);
        $this->memberships->add(new Membership($workspace->id(), $owner->id(), MembershipRole::Owner));
    }
}
