<?php

declare(strict_types=1);

namespace App\Interface\Http\Kanban;

use App\Application\Kanban\CreateIssueType;
use App\Application\Kanban\DeleteIssueType;
use App\Application\Kanban\ListIssueTypes;
use App\Application\Kanban\ReadIssueType;
use App\Application\Kanban\UpdateIssueType;
use App\Application\Security\ReadCurrentUser;
use App\Domain\Kanban\IssueTypeId;
use App\Domain\Kanban\Level;
use App\Domain\Kanban\Position;
use App\Domain\Kanban\Slug;
use App\Domain\Kanban\WorkflowId;
use App\Domain\Tenancy\WorkspaceId;
use App\Interface\Http\BearerToken;
use App\Interface\Http\JsonResponder;
use App\Interface\Http\Tenancy\JsonBody;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class IssueTypeController
{
    public function __construct(
        private ReadCurrentUser $currentUser,
        private ListIssueTypes $listIssueTypes,
        private CreateIssueType $createIssueType,
        private ReadIssueType $readIssueType,
        private UpdateIssueType $updateIssueType,
        private DeleteIssueType $deleteIssueType,
        private IssueTypePresenter $presenter,
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
            'issueTypes' => $this->presenter->presentMany($this->listIssueTypes->execute($user, $workspaceId, $workflowId)),
        ]);
    }

    public function create(
        ServerRequestInterface $request,
        WorkspaceId $workspaceId,
        WorkflowId $workflowId,
    ): ResponseInterface {
        $user = $this->currentUser->execute(BearerToken::fromRequest($request));
        $body = JsonBody::from($request);
        $name = JsonBody::string($body, 'name') ?? '';
        $key = JsonBody::string($body, 'key');
        $issueType = $this->createIssueType->execute(
            $user,
            $workspaceId,
            $workflowId,
            $key === null || $key === '' ? Slug::fromName($name) : new Slug($key),
            $name,
            new Level(JsonBody::integer($body, 'level') ?? 1),
            new Position(JsonBody::integer($body, 'position') ?? 0),
        );

        return $this->responder->respond(['issueType' => $this->presenter->present($issueType)], 201);
    }

    public function show(
        ServerRequestInterface $request,
        WorkspaceId $workspaceId,
        IssueTypeId $id,
    ): ResponseInterface {
        $user = $this->currentUser->execute(BearerToken::fromRequest($request));

        return $this->responder->respond([
            'issueType' => $this->presenter->present($this->readIssueType->execute($user, $workspaceId, $id)),
        ]);
    }

    public function update(
        ServerRequestInterface $request,
        WorkspaceId $workspaceId,
        IssueTypeId $id,
    ): ResponseInterface {
        $user = $this->currentUser->execute(BearerToken::fromRequest($request));
        $body = JsonBody::from($request);
        $level = JsonBody::integer($body, 'level');
        $position = JsonBody::integer($body, 'position');

        return $this->responder->respond([
            'issueType' => $this->presenter->present($this->updateIssueType->execute(
                $user,
                $workspaceId,
                $id,
                JsonBody::string($body, 'name'),
                $level === null ? null : new Level($level),
                $position === null ? null : new Position($position),
            )),
        ]);
    }

    public function delete(
        ServerRequestInterface $request,
        WorkspaceId $workspaceId,
        IssueTypeId $id,
    ): ResponseInterface {
        $user = $this->currentUser->execute(BearerToken::fromRequest($request));
        $this->deleteIssueType->execute($user, $workspaceId, $id);

        return $this->responder->respond(['deleted' => true]);
    }
}
