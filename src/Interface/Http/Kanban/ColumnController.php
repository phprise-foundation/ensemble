<?php

declare(strict_types=1);

namespace App\Interface\Http\Kanban;

use App\Application\Kanban\CreateColumn;
use App\Application\Kanban\DeleteColumn;
use App\Application\Kanban\ListColumns;
use App\Application\Kanban\ReadColumn;
use App\Application\Kanban\UpdateColumn;
use App\Application\Security\ReadCurrentUser;
use App\Domain\Kanban\BoardColumnId;
use App\Domain\Kanban\BoardId;
use App\Domain\Kanban\Position;
use App\Domain\Kanban\StatusId;
use App\Domain\Kanban\StatusIds;
use App\Domain\Tenancy\WorkspaceId;
use App\Interface\Http\BearerToken;
use App\Interface\Http\JsonResponder;
use App\Interface\Http\Tenancy\JsonBody;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class ColumnController
{
    public function __construct(
        private ReadCurrentUser $currentUser,
        private ListColumns $listColumns,
        private CreateColumn $createColumn,
        private ReadColumn $readColumn,
        private UpdateColumn $updateColumn,
        private DeleteColumn $deleteColumn,
        private ColumnPresenter $presenter,
        private JsonResponder $responder,
    ) {
    }

    public function list(
        ServerRequestInterface $request,
        WorkspaceId $workspaceId,
        BoardId $boardId,
    ): ResponseInterface {
        $user = $this->currentUser->execute(BearerToken::fromRequest($request));

        return $this->responder->respond([
            'columns' => $this->presenter->presentMany($this->listColumns->execute($user, $workspaceId, $boardId)),
        ]);
    }

    public function create(
        ServerRequestInterface $request,
        WorkspaceId $workspaceId,
        BoardId $boardId,
    ): ResponseInterface {
        $user = $this->currentUser->execute(BearerToken::fromRequest($request));
        $body = JsonBody::from($request);
        $column = $this->createColumn->execute(
            $user,
            $workspaceId,
            $boardId,
            JsonBody::string($body, 'name') ?? '',
            new Position(JsonBody::integer($body, 'position') ?? 0),
            JsonBody::integer($body, 'wip'),
            $this->statusIds($body),
        );

        return $this->responder->respond(['column' => $this->presenter->present($column)], 201);
    }

    public function show(
        ServerRequestInterface $request,
        WorkspaceId $workspaceId,
        BoardColumnId $id,
    ): ResponseInterface {
        $user = $this->currentUser->execute(BearerToken::fromRequest($request));

        return $this->responder->respond([
            'column' => $this->presenter->present($this->readColumn->execute($user, $workspaceId, $id)),
        ]);
    }

    public function update(
        ServerRequestInterface $request,
        WorkspaceId $workspaceId,
        BoardColumnId $id,
    ): ResponseInterface {
        $user = $this->currentUser->execute(BearerToken::fromRequest($request));
        $body = JsonBody::from($request);
        $position = JsonBody::integer($body, 'position');

        return $this->responder->respond([
            'column' => $this->presenter->present($this->updateColumn->execute(
                $user,
                $workspaceId,
                $id,
                JsonBody::string($body, 'name'),
                $position === null ? null : new Position($position),
                array_key_exists('wip', $body),
                JsonBody::integer($body, 'wip'),
                array_key_exists('statusIds', $body),
                $this->statusIds($body),
            )),
        ]);
    }

    public function delete(
        ServerRequestInterface $request,
        WorkspaceId $workspaceId,
        BoardColumnId $id,
    ): ResponseInterface {
        $user = $this->currentUser->execute(BearerToken::fromRequest($request));
        $this->deleteColumn->execute($user, $workspaceId, $id);

        return $this->responder->respond(['deleted' => true]);
    }

    /** @param array<array-key, mixed> $body */
    private function statusIds(array $body): StatusIds
    {
        return new StatusIds(array_map(
            static fn(string $id): StatusId => new StatusId($id),
            JsonBody::stringList($body, 'statusIds') ?? [],
        ));
    }
}
