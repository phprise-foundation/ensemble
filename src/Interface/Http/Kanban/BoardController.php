<?php

declare(strict_types=1);

namespace App\Interface\Http\Kanban;

use App\Application\Kanban\CreateBoard;
use App\Application\Kanban\DeleteBoard;
use App\Application\Kanban\ListBoards;
use App\Application\Kanban\ListColumns;
use App\Application\Kanban\ReadBoard;
use App\Application\Kanban\UpdateBoard;
use App\Application\Security\ReadCurrentUser;
use App\Domain\Kanban\BoardFilters;
use App\Domain\Kanban\BoardId;
use App\Domain\Kanban\IssueTypeId;
use App\Domain\Kanban\Slug;
use App\Domain\Kanban\StatusId;
use App\Domain\Kanban\WorkflowId;
use App\Domain\Tenancy\WorkspaceId;
use App\Interface\Http\BearerToken;
use App\Interface\Http\JsonResponder;
use App\Interface\Http\Tenancy\JsonBody;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class BoardController
{
    public function __construct(
        private ReadCurrentUser $currentUser,
        private ListBoards $listBoards,
        private CreateBoard $createBoard,
        private ReadBoard $readBoard,
        private UpdateBoard $updateBoard,
        private DeleteBoard $deleteBoard,
        private ListColumns $listColumns,
        private BoardPresenter $presenter,
        private ColumnPresenter $columnPresenter,
        private JsonResponder $responder,
    ) {
    }

    public function list(ServerRequestInterface $request, WorkspaceId $workspaceId): ResponseInterface
    {
        $user = $this->currentUser->execute(BearerToken::fromRequest($request));

        return $this->responder->respond([
            'boards' => $this->presenter->presentMany($this->listBoards->execute($user, $workspaceId)),
        ]);
    }

    public function create(ServerRequestInterface $request, WorkspaceId $workspaceId): ResponseInterface
    {
        $user = $this->currentUser->execute(BearerToken::fromRequest($request));
        $body = JsonBody::from($request);
        $name = JsonBody::string($body, 'name') ?? '';
        $key = JsonBody::string($body, 'key');
        $board = $this->createBoard->execute(
            $user,
            $workspaceId,
            new WorkflowId(JsonBody::string($body, 'workflowId') ?? ''),
            $key === null || $key === '' ? Slug::fromName($name) : new Slug($key),
            $name,
            $this->filters($body),
        );

        return $this->responder->respond(['board' => $this->presenter->present($board)], 201);
    }

    public function show(
        ServerRequestInterface $request,
        WorkspaceId $workspaceId,
        BoardId $id,
    ): ResponseInterface {
        $user = $this->currentUser->execute(BearerToken::fromRequest($request));
        $board = $this->readBoard->execute($user, $workspaceId, $id);

        return $this->responder->respond([
            'board' => $this->presenter->present($board),
            'columns' => $this->columnPresenter->presentMany($this->listColumns->execute($user, $workspaceId, $id)),
        ]);
    }

    public function update(
        ServerRequestInterface $request,
        WorkspaceId $workspaceId,
        BoardId $id,
    ): ResponseInterface {
        $user = $this->currentUser->execute(BearerToken::fromRequest($request));
        $body = JsonBody::from($request);
        $filtersProvided = array_key_exists('filterIssueTypeIds', $body) || array_key_exists('filterStatusIds', $body);

        return $this->responder->respond([
            'board' => $this->presenter->present($this->updateBoard->execute(
                $user,
                $workspaceId,
                $id,
                JsonBody::string($body, 'name'),
                $filtersProvided,
                $this->filters($body),
            )),
        ]);
    }

    public function delete(
        ServerRequestInterface $request,
        WorkspaceId $workspaceId,
        BoardId $id,
    ): ResponseInterface {
        $user = $this->currentUser->execute(BearerToken::fromRequest($request));
        $this->deleteBoard->execute($user, $workspaceId, $id);

        return $this->responder->respond(['deleted' => true]);
    }

    /** @param array<array-key, mixed> $body */
    private function filters(array $body): BoardFilters
    {
        return new BoardFilters(
            array_map(
                static fn(string $id): IssueTypeId => new IssueTypeId($id),
                JsonBody::stringList($body, 'filterIssueTypeIds') ?? [],
            ),
            array_map(
                static fn(string $id): StatusId => new StatusId($id),
                JsonBody::stringList($body, 'filterStatusIds') ?? [],
            ),
        );
    }
}
