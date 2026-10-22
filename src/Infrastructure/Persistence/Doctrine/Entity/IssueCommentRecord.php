<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'issue_comments')]
#[ORM\Index(name: 'idx_issue_comments_issue', columns: ['issue_id'])]
#[ORM\Index(name: 'idx_issue_comments_workspace', columns: ['workspace_id'])]
final class IssueCommentRecord
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 32)]
    private string $id;

    #[ORM\Column(name: 'workspace_id', type: 'string', length: 32)]
    private string $workspaceId;

    #[ORM\Column(name: 'issue_id', type: 'string', length: 32)]
    private string $issueId;

    #[ORM\Column(type: 'string', length: 64)]
    private string $author;

    #[ORM\Column(type: 'text')]
    private string $body;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    public function __construct(
        string $id,
        string $workspaceId,
        string $issueId,
        string $author,
        string $body,
        DateTimeImmutable $createdAt,
    ) {
        $this->id = $id;
        $this->workspaceId = $workspaceId;
        $this->issueId = $issueId;
        $this->author = $author;
        $this->body = $body;
        $this->createdAt = $createdAt;
    }

    public function id(): string { return $this->id; }
    public function workspaceId(): string { return $this->workspaceId; }
    public function issueId(): string { return $this->issueId; }
    public function author(): string { return $this->author; }
    public function body(): string { return $this->body; }
    public function createdAt(): DateTimeImmutable { return $this->createdAt; }
}
