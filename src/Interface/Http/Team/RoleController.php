<?php

declare(strict_types=1);

namespace App\Interface\Http\Team;

use App\Application\Security\ReadCurrentUser;
use App\Application\Team\CreateRole;
use App\Application\Team\DeleteRole;
use App\Application\Team\ListRoles;
use App\Application\Team\ReadRole;
use App\Application\Team\UpdateRole;
use App\Domain\Team\RoleId;
use App\Domain\Team\TeamId;
use App\Domain\Tenancy\WorkspaceId;
use App\Interface\Http\BearerToken;
use App\Interface\Http\JsonResponder;
use App\Interface\Http\Tenancy\JsonBody;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class RoleController
{
    public function __construct(
        private ReadCurrentUser $currentUser,
        private ListRoles $listRoles,
        private CreateRole $createRole,
        private ReadRole $readRole,
        private UpdateRole $updateRole,
        private DeleteRole $deleteRole,
        private RolePresenter $presenter,
        private JsonResponder $responder,
    ) {
    }

    public function list(
        ServerRequestInterface $request,
        WorkspaceId $workspaceId,
        TeamId $teamId,
    ): ResponseInterface {
        $user = $this->currentUser->execute(BearerToken::fromRequest($request));

        return $this->responder->respond([
            'roles' => $this->presenter->presentMany($this->listRoles->execute($user, $workspaceId, $teamId)),
        ]);
    }

    public function create(
        ServerRequestInterface $request,
        WorkspaceId $workspaceId,
        TeamId $teamId,
    ): ResponseInterface {
        $user = $this->currentUser->execute(BearerToken::fromRequest($request));
        $body = JsonBody::from($request);
        $role = $this->createRole->execute(
            $user,
            $workspaceId,
            $teamId,
            JsonBody::string($body, 'name') ?? '',
            JsonBody::string($body, 'description') ?? '',
            JsonBody::boolean($body, 'unblock') ?? false,
            JsonBody::boolean($body, 'interactive') ?? false,
            JsonBody::boolean($body, 'implement') ?? false,
            JsonBody::boolean($body, 'review') ?? false,
            JsonBody::boolean($body, 'test') ?? false,
            JsonBody::boolean($body, 'merge') ?? false,
            JsonBody::boolean($body, 'deploy') ?? false,
        );

        return $this->responder->respond(['role' => $this->presenter->present($role)], 201);
    }

    public function show(
        ServerRequestInterface $request,
        WorkspaceId $workspaceId,
        RoleId $id,
    ): ResponseInterface {
        $user = $this->currentUser->execute(BearerToken::fromRequest($request));

        return $this->responder->respond([
            'role' => $this->presenter->present($this->readRole->execute($user, $workspaceId, $id)),
        ]);
    }

    public function update(
        ServerRequestInterface $request,
        WorkspaceId $workspaceId,
        RoleId $id,
    ): ResponseInterface {
        $user = $this->currentUser->execute(BearerToken::fromRequest($request));
        $body = JsonBody::from($request);

        return $this->responder->respond([
            'role' => $this->presenter->present($this->updateRole->execute(
                $user,
                $workspaceId,
                $id,
                JsonBody::string($body, 'name'),
                JsonBody::string($body, 'description'),
                JsonBody::boolean($body, 'unblock'),
                JsonBody::boolean($body, 'interactive'),
                JsonBody::boolean($body, 'implement'),
                JsonBody::boolean($body, 'review'),
                JsonBody::boolean($body, 'test'),
                JsonBody::boolean($body, 'merge'),
                JsonBody::boolean($body, 'deploy'),
            )),
        ]);
    }

    public function delete(
        ServerRequestInterface $request,
        WorkspaceId $workspaceId,
        RoleId $id,
    ): ResponseInterface {
        $user = $this->currentUser->execute(BearerToken::fromRequest($request));
        $this->deleteRole->execute($user, $workspaceId, $id);

        return $this->responder->respond(['deleted' => true]);
    }
}
