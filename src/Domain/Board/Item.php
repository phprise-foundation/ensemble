<?php

declare(strict_types=1);

namespace App\Domain\Board;

use DateTimeImmutable;
use Phprise\Common\ValueObject\StringObject;

final class Item
{
    private StringObject $title;
    private StringObject $body;
    private Status $status;
    private ?Role $role;
    private StringObject $worker;
    private bool $blocked;
    private StringObject $blockedBy;
    private bool $closed;
    private CommentCollection $comments;
    private DateTimeImmutable $createdAt;

    public function __construct(
        private readonly ItemId $id,
        string $title,
        string $body,
        Status $status,
        ?Role $role,
        DateTimeImmutable $createdAt,
    ) {
        $this->title = new StringObject($title);
        $this->body = new StringObject($body);
        $this->status = $status;
        $this->role = $role;
        $this->worker = new StringObject('');
        $this->blocked = false;
        $this->blockedBy = new StringObject('');
        $this->closed = false;
        $this->comments = new CommentCollection([]);
        $this->createdAt = $createdAt;
    }

    public function id(): ItemId
    {
        return $this->id;
    }

    public function title(): string
    {
        return $this->title->getValue();
    }

    public function body(): string
    {
        return $this->body->getValue();
    }

    public function status(): Status
    {
        return $this->status;
    }

    public function role(): ?Role
    {
        return $this->role;
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

    public function comments(): CommentCollection
    {
        return $this->comments;
    }

    public function moveTo(Status $status): void
    {
        $this->status = $status;
    }

    public function assignRole(?Role $role): void
    {
        $this->role = $role;
    }

    public function assignWorker(string $worker): void
    {
        $this->worker = new StringObject($worker);
    }

    public function clearWorker(): void
    {
        $this->worker = new StringObject('');
    }

    public function rename(string $title): void
    {
        $this->title = new StringObject($title);
    }

    public function describe(string $body): void
    {
        $this->body = new StringObject($body);
    }

    public function block(string $blockedBy): void
    {
        $this->blocked = true;
        $this->blockedBy = new StringObject($blockedBy);
    }

    public function unblock(): void
    {
        $this->blocked = false;
        $this->blockedBy = new StringObject('');
    }

    public function addComment(Comment $comment): void
    {
        $this->comments->append($comment);
    }

    public function close(): void
    {
        $this->closed = true;
        $this->status = Status::Done;
    }
}
