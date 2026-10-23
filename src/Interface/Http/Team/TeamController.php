<?php

declare(strict_types=1);

namespace App\Interface\Http\Team;

use App\Application\Security\ReadCurrentUser;
use App\Application\Team\CreateTeam;
use App\Application\Team\DeleteTeam;
use App\Application\Team\ListTeams;
use App\Application\Team\ReadTeam;
use App\Application\Team\UpdateTeam;
use App\Domain\Team\TeamId;
use App\Domain\Tenancy\WorkspaceId;
use App\Interface\Http\BearerToken;
use App\Interface\Http\JsonResponder;
use App\Interface\Http\Tenancy\JsonBody;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class TeamController
{
    public function __construct(
        private ReadCurrentUser $currentUser,
        private ListTeams $listTeams,
        private CreateTeam $createTeam,
        private ReadTeam $readTeam,
        private UpdateTeam $updateTeam,
        private DeleteTeam $deleteTeam,
        private TeamPresenter $presenter,
        private JsonResponder $responder,
    ) {
    }

    public function list(ServerRequestInterface $request, WorkspaceId $workspaceId): ResponseInterface
    {
        $user = $this->currentUser->execute(BearerToken::fromRequest($request));

        return $this->responder->respond([
            'teams' => $this->presenter->presentMany($this->listTeams->execute($user, $workspaceId)),
        ]);
    }

    public function create(ServerRequestInterface $request, WorkspaceId $workspaceId): ResponseInterface
    {
        $user = $this->currentUser->execute(BearerToken::fromRequest($request));
        $body = JsonBody::from($request);
        $team = $this->createTeam->execute(
            $user,
            $workspaceId,
            JsonBody::string($body, 'name') ?? '',
        );

        return $this->responder->respond(['team' => $this->presenter->present($team)], 201);
    }

    public function show(
        ServerRequestInterface $request,
        WorkspaceId $workspaceId,
        TeamId $id,
    ): ResponseInterface {
        $user = $this->currentUser->execute(BearerToken::fromRequest($request));

        return $this->responder->respond([
            'team' => $this->presenter->present($this->readTeam->execute($user, $workspaceId, $id)),
        ]);
    }

    public function update(
        ServerRequestInterface $request,
        WorkspaceId $workspaceId,
        TeamId $id,
    ): ResponseInterface {
        $user = $this->currentUser->execute(BearerToken::fromRequest($request));
        $body = JsonBody::from($request);

        return $this->responder->respond([
            'team' => $this->presenter->present($this->updateTeam->execute(
                $user,
                $workspaceId,
                $id,
                JsonBody::string($body, 'name'),
            )),
        ]);
    }

    public function delete(
        ServerRequestInterface $request,
        WorkspaceId $workspaceId,
        TeamId $id,
    ): ResponseInterface {
        $user = $this->currentUser->execute(BearerToken::fromRequest($request));
        $this->deleteTeam->execute($user, $workspaceId, $id);

        return $this->responder->respond(['deleted' => true]);
    }
}
