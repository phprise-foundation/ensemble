<?php

declare(strict_types=1);

namespace App\Application\Kanban;

use App\Application\Tenancy\WorkspaceGuard;
use App\Domain\Contract\Kanban\IssueCommentRepository;
use App\Domain\Contract\Kanban\IssueEventRepository;
use App\Domain\Contract\Kanban\IssueRepository;
use App\Domain\Exception\NotFoundException;
use App\Domain\Kanban\IssueComment;
use App\Domain\Kanban\IssueCommentId;
use App\Domain\Kanban\IssueEvent;
use App\Domain\Kanban\IssueEventId;
use App\Domain\Kanban\IssueEventType;
use App\Domain\Kanban\IssueKey;
use App\Domain\Tenancy\User;
use App\Domain\Tenancy\WorkspaceId;

final readonly class CommentIssue
{
    public function __construct(
        private IssueRepository $issues,
        private IssueCommentRepository $comments,
        private IssueEventRepository $events,
        private WorkspaceGuard $guard,
    ) {
    }

    public function execute(User $actor, WorkspaceId $workspaceId, IssueKey $key, string $body): IssueComment
    {
        $this->guard->assertMember($actor, $workspaceId);
        $issue = $this->issues->find($workspaceId, $key) ?? throw NotFoundException::issue();
        $comment = new IssueComment(
            IssueCommentId::generate(),
            $workspaceId,
            $issue->id(),
            $actor->login()->value(),
            $body,
        );
        $this->comments->add($comment);
        $this->events->add(new IssueEvent(
            IssueEventId::generate(),
            $workspaceId,
            $issue->id(),
            IssueEventType::Comment,
            null,
            null,
            $actor->login()->value(),
            mb_substr(trim($body), 0, 140),
        ));

        return $comment;
    }
}
