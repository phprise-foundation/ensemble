<?php

declare(strict_types=1);

namespace App\Domain\Kanban;

use App\Domain\Exception\ValidationException;
use App\Domain\Tenancy\WorkspaceId;
use DateTimeImmutable;
use Phprise\Common\ValueObject\StringObject;

final readonly class IssueComment
{
    private StringObject $author;
    private StringObject $body;

    public function __construct(
        private IssueCommentId $id,
        private WorkspaceId $workspaceId,
        private IssueId $issueId,
        string $author,
        string $body,
        private ?DateTimeImmutable $createdAt = null,
    ) {
        $this->author = new StringObject($this->validAuthor($author));
        $this->body = new StringObject($this->validBody($body));
    }

    public function id(): IssueCommentId
    {
        return $this->id;
    }

    public function workspaceId(): WorkspaceId
    {
        return $this->workspaceId;
    }

    public function issueId(): IssueId
    {
        return $this->issueId;
    }

    public function author(): string
    {
        return $this->author->getValue();
    }

    public function body(): string
    {
        return $this->body->getValue();
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt ?? new DateTimeImmutable();
    }

    private function validAuthor(string $author): string
    {
        $normalized = trim($author);
        if ($normalized === '' || mb_strlen($normalized) > 64) {
            throw new ValidationException('author', 'Author must have 1 to 64 characters.');
        }

        return $normalized;
    }

    private function validBody(string $body): string
    {
        if (trim($body) === '' || mb_strlen($body) > 1023) {
            throw new ValidationException('body', 'Comment must have 1 to 1023 characters.');
        }

        return $body;
    }
}
