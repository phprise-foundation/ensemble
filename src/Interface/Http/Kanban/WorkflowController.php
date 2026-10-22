<?php

declare(strict_types=1);

namespace App\Interface\Http\Kanban;

use App\Application\Kanban\CreateWorkflow;
use App\Application\Kanban\DeleteWorkflow;
use App\Application\Kanban\ListWorkflows;
use App\Application\Kanban\ReadWorkflow;
use App\Application\Kanban\UpdateWorkflow;
use App\Application\Security\ReadCurrentUser;
use App\Domain\Kanban\Slug;
use App\Domain\Kanban\WorkflowId;
use App\Domain\Tenancy\WorkspaceId;
use App\Interface\Http\BearerToken;
use App\Interface\Http\JsonResponder;
use App\Interface\Http\Tenancy\JsonBody;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class WorkflowController
{
    public function __construct(
        private ReadCurrentUser $currentUser,
        private ListWorkflows $listWorkflows,
        private CreateWorkflow $createWorkflow,
        private ReadWorkflow $readWorkflow,
        private UpdateWorkflow $updateWorkflow,
        private DeleteWorkflow $deleteWorkflow,
        private WorkflowPresenter $presenter,
        private JsonResponder $responder,
    ) {
    }

    public function list(ServerRequestInterface $request, WorkspaceId $workspaceId): ResponseInterface
    {
        $user = $this->currentUser->execute(BearerToken::fromRequest($request));

        return $this->responder->respond([
            'workflows' => $this->presenter->presentMany($this->listWorkflows->execute($user, $workspaceId)),
        ]);
    }

    public function create(ServerRequestInterface $request, WorkspaceId $workspaceId): ResponseInterface
    {
        $user = $this->currentUser->execute(BearerToken::fromRequest($request));
        $body = JsonBody::from($request);
        $name = JsonBody::string($body, 'name') ?? '';
        $key = JsonBody::string($body, 'key');
        $workflow = $this->createWorkflow->execute(
            $user,
            $workspaceId,
            $key === null || $key === '' ? Slug::fromName($name) : new Slug($key),
            $name,
        );

        return $this->responder->respond(['workflow' => $this->presenter->present($workflow)], 201);
    }

    public function show(ServerRequestInterface $request, WorkspaceId $workspaceId, WorkflowId $id): ResponseInterface
    {
        $user = $this->currentUser->execute(BearerToken::fromRequest($request));

        return $this->responder->respond([
            'workflow' => $this->presenter->present($this->readWorkflow->execute($user, $workspaceId, $id)),
        ]);
    }

    public function update(ServerRequestInterface $request, WorkspaceId $workspaceId, WorkflowId $id): ResponseInterface
    {
        $user = $this->currentUser->execute(BearerToken::fromRequest($request));
        $body = JsonBody::from($request);

        return $this->responder->respond([
            'workflow' => $this->presenter->present($this->updateWorkflow->execute(
                $user,
                $workspaceId,
                $id,
                JsonBody::string($body, 'name'),
            )),
        ]);
    }

    public function delete(ServerRequestInterface $request, WorkspaceId $workspaceId, WorkflowId $id): ResponseInterface
    {
        $user = $this->currentUser->execute(BearerToken::fromRequest($request));
        $this->deleteWorkflow->execute($user, $workspaceId, $id);

        return $this->responder->respond(['deleted' => true]);
    }
}
