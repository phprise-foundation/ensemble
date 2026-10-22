<?php

declare(strict_types=1);

namespace App\Infrastructure\Bootstrap;

use App\Application\Security\AuthenticateUser;
use App\Application\Security\ReadCurrentUser;
use App\Application\Tenancy\AddWorkspaceMember;
use App\Application\Tenancy\CreateWorkspace;
use App\Application\Tenancy\ListWorkspaceMembers;
use App\Application\Tenancy\ListWorkspaces;
use App\Application\Tenancy\ReadWorkspace;
use App\Application\Tenancy\RegisterUser;
use App\Application\Tenancy\RemoveWorkspaceMember;
use App\Application\Tenancy\UpdateWorkspace;
use App\Application\Tenancy\WorkspaceGuard;
use App\Infrastructure\Authentication\ArgonPasswordHasher;
use App\Infrastructure\Authentication\RandomTokenGenerator;
use App\Infrastructure\Persistence\Doctrine\DoctrineEntityManagerFactory;
use App\Infrastructure\Persistence\Doctrine\Mapper\MembershipRecordMapper;
use App\Infrastructure\Persistence\Doctrine\Mapper\SessionRecordMapper;
use App\Infrastructure\Persistence\Doctrine\Mapper\UserRecordMapper;
use App\Infrastructure\Persistence\Doctrine\Mapper\WorkspaceRecordMapper;
use App\Infrastructure\Persistence\Doctrine\Repository\DoctrineMembershipRepository;
use App\Infrastructure\Persistence\Doctrine\Repository\DoctrineSessionRepository;
use App\Infrastructure\Persistence\Doctrine\Repository\DoctrineUserRepository;
use App\Infrastructure\Persistence\Doctrine\Repository\DoctrineWorkspaceRepository;
use App\Interface\Http\JsonResponder;
use App\Interface\Http\Tenancy\MemberPresenter;
use App\Interface\Http\Tenancy\SessionController;
use App\Interface\Http\Tenancy\TenancyErrorResponder;
use App\Interface\Http\Tenancy\TenancyRouter;
use App\Interface\Http\Tenancy\UserController;
use App\Interface\Http\Tenancy\UserPresenter;
use App\Interface\Http\Tenancy\WorkspaceController;
use App\Interface\Http\Tenancy\WorkspacePresenter;

final readonly class TenancyBootstrap
{
    public function __construct(
        private DoctrineEntityManagerFactory $entityManagers,
        private JsonResponder $responder,
    ) {
    }

    public function router(): TenancyRouter
    {
        $entityManager = $this->entityManagers->create();
        $users = new DoctrineUserRepository($entityManager, new UserRecordMapper());
        $workspaces = new DoctrineWorkspaceRepository($entityManager, new WorkspaceRecordMapper());
        $memberships = new DoctrineMembershipRepository($entityManager, new MembershipRecordMapper());
        $sessions = new DoctrineSessionRepository($entityManager, new SessionRecordMapper());
        $hasher = new ArgonPasswordHasher();
        $guard = new WorkspaceGuard($workspaces, $memberships);
        $currentUser = new ReadCurrentUser($sessions, $users);
        $userPresenter = new UserPresenter();
        $workspacePresenter = new WorkspacePresenter();
        $memberPresenter = new MemberPresenter($userPresenter);

        return new TenancyRouter(
            new SessionController(
                new AuthenticateUser($users, $sessions, $hasher, new RandomTokenGenerator()),
                $currentUser,
                $sessions,
                new ListWorkspaces($workspaces, $memberships),
                $userPresenter,
                $workspacePresenter,
                $this->responder,
            ),
            new UserController(
                $currentUser,
                $guard,
                new RegisterUser($users, $hasher),
                $users,
                $userPresenter,
                $this->responder,
            ),
            new WorkspaceController(
                $currentUser,
                $guard,
                new ListWorkspaces($workspaces, $memberships),
                new CreateWorkspace($workspaces, $memberships),
                new ReadWorkspace($guard),
                new UpdateWorkspace($workspaces, $guard),
                new ListWorkspaceMembers($guard, $memberships, $users),
                new AddWorkspaceMember($guard, $users, $memberships),
                new RemoveWorkspaceMember($guard, $memberships),
                $workspacePresenter,
                $memberPresenter,
                $this->responder,
            ),
            new TenancyErrorResponder($this->responder),
            $this->responder,
        );
    }
}
