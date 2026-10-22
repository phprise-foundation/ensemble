<?php

declare(strict_types=1);

namespace App\Interface\Http\Kanban;

use App\Application\Kanban\CommentIssue;
use App\Application\Kanban\CreateIssue;
use App\Application\Kanban\ListIssueComments;
use App\Application\Kanban\ListIssueEvents;
use App\Application\Kanban\ListIssues;
use App\Application\Kanban\MoveIssue;
use App\Application\Kanban\ReadIssue;
use App\Application\Tenancy\ReadWorkspace;
use App\Application\Kanban\UpdateIssue;
use App\Application\Security\ReadCurrentUser;
use App\Domain\Kanban\BoardId;
use App\Domain\Kanban\IssueKey;
use App\Domain\Kanban\IssueTypeId;
use App\Domain\Kanban\StatusId;
use App\Domain\Tenancy\WorkspaceId;
use App\Interface\Http\BearerToken;
use App\Interface\Http\JsonResponder;
use App\Interface\Http\Tenancy\JsonBody;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class IssueController
{
    public function __construct(
        private ReadCurrentUser $currentUser,
        private ReadWorkspace $readWorkspace,
        private ListIssues $listIssues,
        private CreateIssue $createIssue,
        private ReadIssue $readIssue,
        private UpdateIssue $updateIssue,
        private MoveIssue $moveIssue,
        private CommentIssue $commentIssue,
        private ListIssueComments $listComments,
        private ListIssueEvents $listEvents,
        private IssuePresenter $presenter,
        private IssueCommentPresenter $commentPresenter,
        private IssueEventPresenter $eventPresenter,
        private JsonResponder $responder,
    ) {
    }

    public function list(ServerRequestInterface $request, WorkspaceId $workspaceId): ResponseInterface
    {
        $user = $this->currentUser->execute(BearerToken::fromRequest($request));
        $query = $request->getQueryParams();
        $boardId = is_string($query['boardId'] ?? null)
            ? new BoardId((string) ($query['boardId'] ?? ''))
            : null;

        return $this->responder->respond([
            'issues' => $this->presenter->presentMany($this->listIssues->execute($user, $workspaceId, $boardId)),
        ]);
    }

    public function create(ServerRequestInterface $request, WorkspaceId $workspaceId): ResponseInterface
    {
        $user = $this->currentUser->execute(BearerToken::fromRequest($request));
        $workspace = $this->readWorkspace->execute($user, $workspaceId);
        $body = JsonBody::from($request);
        $status = JsonBody::string($body, 'statusId');
        $issue = $this->createIssue->execute(
            $user,
            $workspaceId,
            $workspace->prefix(),
            new IssueTypeId(JsonBody::string($body, 'issueTypeId') ?? ''),
            $status === null || $status === '' ? null : new StatusId($status),
            JsonBody::string($body, 'summary') ?? '',
            JsonBody::string($body, 'description') ?? '',
            JsonBody::string($body, 'assigneeId'),
            $this->fieldValues($body),
        );

        return $this->responder->respond(['issue' => $this->presenter->present($issue)], 201);
    }

    public function show(
        ServerRequestInterface $request,
        WorkspaceId $workspaceId,
        IssueKey $key,
    ): ResponseInterface {
        $user = $this->currentUser->execute(BearerToken::fromRequest($request));

        return $this->responder->respond([
            'issue' => $this->presenter->present($this->readIssue->execute($user, $workspaceId, $key)),
            'comments' => $this->commentPresenter->presentMany($this->listComments->execute($user, $workspaceId, $key)),
            'history' => $this->eventPresenter->presentMany($this->listEvents->execute($user, $workspaceId, $key)),
        ]);
    }

    public function update(
        ServerRequestInterface $request,
        WorkspaceId $workspaceId,
        IssueKey $key,
    ): ResponseInterface {
        $user = $this->currentUser->execute(BearerToken::fromRequest($request));
        $body = JsonBody::from($request);

        return $this->responder->respond([
            'issue' => $this->presenter->present($this->updateIssue->execute(
                $user,
                $workspaceId,
                $key,
                JsonBody::string($body, 'summary'),
                JsonBody::string($body, 'description'),
                array_key_exists('assigneeId', $body),
                JsonBody::string($body, 'assigneeId'),
                array_key_exists('blocked', $body),
                JsonBody::boolean($body, 'blocked'),
                array_key_exists('fields', $body),
                $this->fieldValues($body),
            )),
        ]);
    }

    public function move(
        ServerRequestInterface $request,
        WorkspaceId $workspaceId,
        IssueKey $key,
    ): ResponseInterface {
        $user = $this->currentUser->execute(BearerToken::fromRequest($request));
        $body = JsonBody::from($request);

        return $this->responder->respond([
            'issue' => $this->presenter->present($this->moveIssue->execute(
                $user,
                $workspaceId,
                $key,
                new StatusId(JsonBody::string($body, 'statusId') ?? ''),
            )),
        ]);
    }

    public function comment(
        ServerRequestInterface $request,
        WorkspaceId $workspaceId,
        IssueKey $key,
    ): ResponseInterface {
        $user = $this->currentUser->execute(BearerToken::fromRequest($request));
        $body = JsonBody::from($request);
        $comment = $this->commentIssue->execute(
            $user,
            $workspaceId,
            $key,
            JsonBody::string($body, 'body') ?? '',
        );

        return $this->responder->respond(['comment' => $this->commentPresenter->present($comment)], 201);
    }

    /**
     * @param array<array-key, mixed> $body
     * @return array<string, mixed>
     */
    private function fieldValues(array $body): array
    {
        $raw = $body['fields'] ?? null;
        if (!is_array($raw)) {
            return [];
        }

        /** @var array<string, mixed> $raw */
        return $raw;
    }
}
