<?php

declare(strict_types=1);

namespace App\Interface\Http\Kanban;

use App\Application\Kanban\CreateStatus;
use App\Application\Kanban\DeleteStatus;
use App\Application\Kanban\ListStatuses;
use App\Application\Kanban\ReadStatus;
use App\Application\Kanban\UpdateStatus;
use App\Application\Security\ReadCurrentUser;
use App\Domain\Exception\ValidationException;
use App\Domain\Kanban\IssueTypeId;
use App\Domain\Kanban\Position;
use App\Domain\Kanban\Slug;
use App\Domain\Kanban\StatusId;
use App\Domain\Kanban\StatusType;
use App\Domain\Kanban\WorkflowId;
use App\Domain\Team\RoleIds;
use App\Domain\Tenancy\WorkspaceId;
use App\Interface\Http\BearerToken;
use App\Interface\Http\JsonResponder;
use App\Interface\Http\Tenancy\JsonBody;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class StatusController
{
    public function __construct(
        private ReadCurrentUser $currentUser,
        private ListStatuses $listStatuses,
        private CreateStatus $createStatus,
        private ReadStatus $readStatus,
        private UpdateStatus $updateStatus,
        private DeleteStatus $deleteStatus,
        private StatusPresenter $presenter,
        private JsonResponder $responder,
    ) {
    }

    public function list(
        ServerRequestInterface $request,
        WorkspaceId $workspaceId,
        WorkflowId $workflowId,
    ): ResponseInterface {
        $user = $this->currentUser->execute(BearerToken::fromRequest($request));
        $query = $request->getQueryParams();
        $issueTypeId = is_string($query['issueTypeId'] ?? null)
            ? new IssueTypeId((string) ($query['issueTypeId'] ?? ''))
            : null;

        return $this->responder->respond([
            'statuses' => $this->presenter->presentMany($this->listStatuses->execute($user, $workspaceId, $workflowId, $issueTypeId)),
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
        $status = $this->createStatus->execute(
            $user,
            $workspaceId,
            $workflowId,
            new IssueTypeId(JsonBody::string($body, 'issueTypeId') ?? ''),
            $key === null || $key === '' ? Slug::fromName($name) : new Slug($key),
            $name,
            StatusType::fromString(JsonBody::string($body, 'type') ?? ''),
            JsonBody::string($body, 'color') ?? '#6b7280',
            new Position(JsonBody::integer($body, 'position') ?? 0),
            $this->roleIdsFrom($body) ?? new RoleIds(),
        );

        return $this->responder->respond(['status' => $this->presenter->present($status)], 201);
    }

    public function show(
        ServerRequestInterface $request,
        WorkspaceId $workspaceId,
        StatusId $id,
    ): ResponseInterface {
        $user = $this->currentUser->execute(BearerToken::fromRequest($request));

        return $this->responder->respond([
            'status' => $this->presenter->present($this->readStatus->execute($user, $workspaceId, $id)),
        ]);
    }

    public function update(
        ServerRequestInterface $request,
        WorkspaceId $workspaceId,
        StatusId $id,
    ): ResponseInterface {
        $user = $this->currentUser->execute(BearerToken::fromRequest($request));
        $body = JsonBody::from($request);
        $position = JsonBody::integer($body, 'position');
        $type = JsonBody::string($body, 'type');

        return $this->responder->respond([
            'status' => $this->presenter->present($this->updateStatus->execute(
                $user,
                $workspaceId,
                $id,
                JsonBody::string($body, 'name'),
                $type === null ? null : StatusType::fromString($type),
                JsonBody::string($body, 'color'),
                $position === null ? null : new Position($position),
                $this->roleIdsFrom($body),
            )),
        ]);
    }

    public function delete(
        ServerRequestInterface $request,
        WorkspaceId $workspaceId,
        StatusId $id,
    ): ResponseInterface {
        $user = $this->currentUser->execute(BearerToken::fromRequest($request));
        $this->deleteStatus->execute($user, $workspaceId, $id);

        return $this->responder->respond(['deleted' => true]);
    }

    /**
     * @param array<array-key, mixed> $body
     */
    private function roleIdsFrom(array $body): ?RoleIds
    {
        if (!array_key_exists('roleIds', $body)) {
            return null;
        }

        $values = JsonBody::stringList($body, 'roleIds');
        if ($values === null) {
            throw new ValidationException('roleIds', 'Role ids must be a list of strings.');
        }

        try {
            return RoleIds::fromStrings($values);
        } catch (InvalidArgumentException) {
            throw new ValidationException('roleIds', 'Every role id must be a 32-character hexadecimal string.');
        }
    }
}
