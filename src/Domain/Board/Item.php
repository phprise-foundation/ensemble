<?php

declare(strict_types=1);

namespace App\Domain\Board;

use App\Domain\Agent\AgentId;
use App\Domain\Workflow\ColumnId;
use DateTimeImmutable;
use Phprise\Common\ValueObject\StringObject;

final class Item
{
    private StringObject $title;
    private StringObject $body;
    private ?AgentId $agent;
    private StringObject $worker;
    private bool $blocked;
    private StringObject $blockedBy;
    private bool $closed;
    private CommentCollection $comments;
    private DateTimeImmutable $updatedAt;
    private DateTimeImmutable $enteredAt;

    public function __construct(
        private readonly ItemId $id,
        private ColumnId $column,
        string $title,
        string $body,
        ?AgentId $agent,
        private readonly DateTimeImmutable $createdAt,
    ) {
        $this->title = new StringObject($title);
        $this->body = new StringObject($body);
        $this->agent = $agent;
        $this->worker = new StringObject('');
        $this->blocked = false;
        $this->blockedBy = new StringObject('');
        $this->closed = false;
        $this->comments = new CommentCollection([]);
        $this->updatedAt = $createdAt;
        $this->enteredAt = $createdAt;
    }

    public function id(): ItemId
    {
        return $this->id;
    }

    public function column(): ColumnId
    {
        return $this->column;
    }

    public function agent(): ?AgentId
    {
        return $this->agent;
    }

    public function title(): string
    {
        return $this->title->getValue();
    }

    public function body(): string
    {
        return $this->body->getValue();
    }

    public function worker(): string
    {
        return $this->worker->getValue();
    }

    public function blocked(): bool
    {
        return $this->blocked;
    }

    public function blockedBy(): string
    {
        return $this->blockedBy->getValue();
    }

    public function closed(): bool
    {
        return $this->closed;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function enteredAt(): DateTimeImmutable
    {
        return $this->enteredAt;
    }

    public function comments(): CommentCollection
    {
        return $this->comments;
    }

    public function moveTo(ColumnId $column, DateTimeImmutable $at): void
    {
        $this->column = $column;
        $this->enteredAt = $at;
        $this->touch($at);
    }

    public function assignAgent(?AgentId $agent, DateTimeImmutable $at): void
    {
        $this->agent = $agent;
        $this->touch($at);
    }

    public function assignWorker(string $worker, DateTimeImmutable $at): void
    {
        $this->worker = new StringObject($worker);
        $this->touch($at);
    }

    public function clearWorker(DateTimeImmutable $at): void
    {
        $this->assignWorker('', $at);
    }

    public function rename(string $title, DateTimeImmutable $at): void
    {
        $this->title = new StringObject($title);
        $this->touch($at);
    }

    public function describe(string $body, DateTimeImmutable $at): void
    {
        $this->body = new StringObject($body);
        $this->touch($at);
    }

    public function block(string $blockedBy, DateTimeImmutable $at): void
    {
        $this->blocked = true;
        $this->blockedBy = new StringObject($blockedBy);
        $this->touch($at);
    }

    public function unblock(DateTimeImmutable $at): void
    {
        $this->blocked = false;
        $this->blockedBy = new StringObject('');
        $this->touch($at);
    }

    public function addComment(Comment $comment, DateTimeImmutable $at): void
    {
        $this->comments->append($comment);
        $this->touch($at);
    }

    public function close(DateTimeImmutable $at): void
    {
        $this->closed = true;
        $this->touch($at);
    }

    public function restoreTimestamps(DateTimeImmutable $updatedAt, DateTimeImmutable $enteredAt): void
    {
        $this->updatedAt = $updatedAt;
        $this->enteredAt = $enteredAt;
    }

    private function touch(DateTimeImmutable $at): void
    {
        $this->updatedAt = $at;
    }
}
