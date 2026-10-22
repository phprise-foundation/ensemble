<?php

declare(strict_types=1);

namespace App\Interface\Http\Tenancy;

use App\Application\Security\ReadCurrentUser;
use App\Application\Tenancy\AddWorkspaceMember;
use App\Application\Tenancy\CreateWorkspace;
use App\Application\Tenancy\ListWorkspaceMembers;
use App\Application\Tenancy\ListWorkspaces;
use App\Application\Tenancy\ReadWorkspace;
use App\Application\Tenancy\RemoveWorkspaceMember;
use App\Application\Tenancy\UpdateWorkspace;
use App\Application\Tenancy\WorkspaceGuard;
use App\Domain\Tenancy\Login;
use App\Domain\Tenancy\MembershipRole;
use App\Domain\Tenancy\UserId;
use App\Domain\Tenancy\WorkspaceId;
use App\Domain\Tenancy\WorkspacePrefix;
use App\Interface\Http\BearerToken;
use App\Interface\Http\JsonResponder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class WorkspaceController
{
    public function __construct(
        private ReadCurrentUser $currentUser,
        private WorkspaceGuard $guard,
        private ListWorkspaces $listWorkspaces,
        private CreateWorkspace $createWorkspace,
        private ReadWorkspace $readWorkspace,
        private UpdateWorkspace $updateWorkspace,
        private ListWorkspaceMembers $listMembers,
        private AddWorkspaceMember $addWorkspaceMember,
        private RemoveWorkspaceMember $removeWorkspaceMember,
        private WorkspacePresenter $workspaces,
        private MemberPresenter $memberPresenter,
        private JsonResponder $responder,
    ) {
    }

    public function list(ServerRequestInterface $request): ResponseInterface
    {
        $user = $this->currentUser->execute(BearerToken::fromRequest($request));

        return $this->responder->respond(['workspaces' => $this->workspaces->presentMany($this->listWorkspaces->execute($user))]);
    }

    public function create(ServerRequestInterface $request): ResponseInterface
    {
        $user = $this->currentUser->execute(BearerToken::fromRequest($request));
        $body = JsonBody::from($request);
        $name = JsonBody::string($body, 'name') ?? '';
        $prefix = JsonBody::string($body, 'prefix');
        $workspace = $this->createWorkspace->execute(
            $user,
            $name,
            $prefix === null || $prefix === '' ? WorkspacePrefix::fromName($name) : new WorkspacePrefix($prefix),
        );

        return $this->responder->respond(['workspace' => $this->workspaces->present($workspace, MembershipRole::Owner)], 201);
    }

    public function show(ServerRequestInterface $request, WorkspaceId $workspaceId): ResponseInterface
    {
        $user = $this->currentUser->execute(BearerToken::fromRequest($request));
        $workspace = $this->readWorkspace->execute($user, $workspaceId);

        return $this->responder->respond([
            'workspace' => $this->workspaces->present($workspace, $this->guard->roleOf($user, $workspaceId)),
        ]);
    }

    public function update(ServerRequestInterface $request, WorkspaceId $workspaceId): ResponseInterface
    {
        $user = $this->currentUser->execute(BearerToken::fromRequest($request));
        $body = JsonBody::from($request);
        $prefix = JsonBody::string($body, 'prefix');
        $workspace = $this->updateWorkspace->execute(
            $user,
            $workspaceId,
            JsonBody::string($body, 'name'),
            $prefix === null || $prefix === '' ? null : new WorkspacePrefix($prefix),
        );

        return $this->responder->respond([
            'workspace' => $this->workspaces->present($workspace, $this->guard->roleOf($user, $workspaceId)),
        ]);
    }

    public function members(ServerRequestInterface $request, WorkspaceId $workspaceId): ResponseInterface
    {
        $user = $this->currentUser->execute(BearerToken::fromRequest($request));

        return $this->responder->respond([
            'members' => $this->memberPresenter->presentMany($this->listMembers->execute($user, $workspaceId)),
        ]);
    }

    public function addMember(ServerRequestInterface $request, WorkspaceId $workspaceId): ResponseInterface
    {
        $user = $this->currentUser->execute(BearerToken::fromRequest($request));
        $body = JsonBody::from($request);
        $member = $this->addWorkspaceMember->execute(
            $user,
            $workspaceId,
            new Login(JsonBody::string($body, 'login') ?? ''),
            MembershipRole::fromString(JsonBody::string($body, 'role') ?? MembershipRole::Member->value),
        );

        return $this->responder->respond(['member' => $this->memberPresenter->present($member)]);
    }

    public function removeMember(ServerRequestInterface $request, WorkspaceId $workspaceId, UserId $userId): ResponseInterface
    {
        $user = $this->currentUser->execute(BearerToken::fromRequest($request));
        $this->removeWorkspaceMember->execute($user, $workspaceId, $userId);

        return $this->responder->respond(['deleted' => true]);
    }
}
