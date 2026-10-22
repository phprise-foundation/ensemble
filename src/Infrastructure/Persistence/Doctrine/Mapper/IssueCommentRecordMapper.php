<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Mapper;

use App\Domain\Kanban\IssueComment;
use App\Domain\Kanban\IssueCommentId;
use App\Domain\Kanban\IssueId;
use App\Domain\Tenancy\WorkspaceId;
use App\Infrastructure\Persistence\Doctrine\Entity\IssueCommentRecord;

final class IssueCommentRecordMapper
{
    public function toDomain(IssueCommentRecord $record): IssueComment
    {
        return new IssueComment(
            new IssueCommentId($record->id()),
            new WorkspaceId($record->workspaceId()),
            new IssueId($record->issueId()),
            $record->author(),
            $record->body(),
            $record->createdAt(),
        );
    }

    public function newRecord(IssueComment $comment): IssueCommentRecord
    {
        return new IssueCommentRecord(
            $comment->id()->value(),
            $comment->workspaceId()->value(),
            $comment->issueId()->value(),
            $comment->author(),
            $comment->body(),
            $comment->createdAt(),
        );
    }
}
