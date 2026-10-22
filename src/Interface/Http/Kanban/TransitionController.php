<?php

declare(strict_types=1);

namespace App\Interface\Http\Kanban;

use App\Application\Kanban\CreateTransition;
use App\Application\Kanban\DeleteTransition;
use App\Application\Kanban\ListTransitions;
use App\Application\Kanban\ReadTransition;
use App\Application\Kanban\UpdateTransition;
use App\Application\Security\ReadCurrentUser;
use App\Domain\Kanban\Slug;
use App\Domain\Kanban\StatusId;
use App\Domain\Kanban\TransitionActor;
use App\Domain\Kanban\TransitionId;
use App\Domain\Kanban\WorkflowId;
use App\Domain\Tenancy\WorkspaceId;
use App\Interface\Http\BearerToken;
use App\Interface\Http\JsonResponder;
use App\Interface\Http\Tenancy\JsonBody;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class TransitionController
{
    public function __construct(
        private ReadCurrentUser $currentUser,
        private ListTransitions $listTransitions,
        private CreateTransition $createTransition,
        private ReadTransition $readTransition,
        private UpdateTransition $updateTransition,
        private DeleteTransition $deleteTransition,
        private TransitionPresenter $presenter,
        private JsonResponder $responder,
    ) {
    }

    public function list(
        ServerRequestInterface $request,
        WorkspaceId $workspaceId,
        WorkflowId $workflowId,
    ): ResponseInterface {
        $user = $this->currentUser->execute(BearerToken::fromRequest($request));

        return $this->responder->respond([
            'transitions' => $this->presenter->presentMany($this->listTransitions->execute($user, $workspaceId, $workflowId)),
        ]);
    }

    public function create(
        ServerRequestInterface $request,
        WorkspaceId $workspaceId,
        WorkflowId $workflowId,
    ): ResponseInterface {
        $user = $this->currentUser->execute(BearerToken::fromRequest($request));
        $body = JsonBody::from($request);
        $outcome = JsonBody::string($body, 'outcome');
        $transition = $this->createTransition->execute(
            $user,
            $workspaceId,
            $workflowId,
            new StatusId(JsonBody::string($body, 'fromStatusId') ?? ''),
            new StatusId(JsonBody::string($body, 'toStatusId') ?? ''),
            TransitionActor::fromString(JsonBody::string($body, 'actor') ?? TransitionActor::Both->value),
            $outcome === null || $outcome === '' ? null : new Slug($outcome),
        );

        return $this->responder->respond(['transition' => $this->presenter->present($transition)], 201);
    }

    public function show(
        ServerRequestInterface $request,
        WorkspaceId $workspaceId,
        TransitionId $id,
    ): ResponseInterface {
        $user = $this->currentUser->execute(BearerToken::fromRequest($request));

        return $this->responder->respond([
            'transition' => $this->presenter->present($this->readTransition->execute($user, $workspaceId, $id)),
        ]);
    }

    public function update(
        ServerRequestInterface $request,
        WorkspaceId $workspaceId,
        TransitionId $id,
    ): ResponseInterface {
        $user = $this->currentUser->execute(BearerToken::fromRequest($request));
        $body = JsonBody::from($request);
        $actor = JsonBody::string($body, 'actor');
        $outcome = JsonBody::string($body, 'outcome');

        return $this->responder->respond([
            'transition' => $this->presenter->present($this->updateTransition->execute(
                $user,
                $workspaceId,
                $id,
                $actor === null ? null : TransitionActor::fromString($actor),
                array_key_exists('outcome', $body),
                $outcome === null || $outcome === '' ? null : new Slug($outcome),
            )),
        ]);
    }

    public function delete(
        ServerRequestInterface $request,
        WorkspaceId $workspaceId,
        TransitionId $id,
    ): ResponseInterface {
        $user = $this->currentUser->execute(BearerToken::fromRequest($request));
        $this->deleteTransition->execute($user, $workspaceId, $id);

        return $this->responder->respond(['deleted' => true]);
    }
}
