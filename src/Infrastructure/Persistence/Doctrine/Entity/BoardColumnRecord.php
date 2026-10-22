<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'board_columns')]
#[ORM\Index(name: 'idx_board_columns_board', columns: ['board_id'])]
#[ORM\Index(name: 'idx_board_columns_workspace', columns: ['workspace_id'])]
final class BoardColumnRecord
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 32)]
    private string $id;

    #[ORM\Column(name: 'workspace_id', type: 'string', length: 32)]
    private string $workspaceId;

    #[ORM\Column(name: 'board_id', type: 'string', length: 32)]
    private string $boardId;

    #[ORM\Column(type: 'string', length: 80)]
    private string $name;

    #[ORM\Column(type: 'integer')]
    private int $position;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $wip;

    /** @var list<string> */
    #[ORM\Column(name: 'status_ids', type: 'json')]
    private array $statusIds;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private DateTimeImmutable $updatedAt;

    /** @param list<string> $statusIds */
    public function __construct(
        string $id,
        string $workspaceId,
        string $boardId,
        string $name,
        int $position,
        ?int $wip,
        array $statusIds,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt,
    ) {
        $this->id = $id;
        $this->workspaceId = $workspaceId;
        $this->boardId = $boardId;
        $this->name = $name;
        $this->position = $position;
        $this->wip = $wip;
        $this->statusIds = $statusIds;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    public function id(): string { return $this->id; }
    public function workspaceId(): string { return $this->workspaceId; }
    public function boardId(): string { return $this->boardId; }
    public function name(): string { return $this->name; }
    public function position(): int { return $this->position; }
    public function wip(): ?int { return $this->wip; }
    public function statusIds(): array { return $this->statusIds; }
    public function createdAt(): DateTimeImmutable { return $this->createdAt; }
    public function updatedAt(): DateTimeImmutable { return $this->updatedAt; }

    /** @param list<string> $statusIds */
    public function sync(string $name, int $position, ?int $wip, array $statusIds, DateTimeImmutable $updatedAt): void
    {
        $this->name = $name;
        $this->position = $position;
        $this->wip = $wip;
        $this->statusIds = $statusIds;
        $this->updatedAt = $updatedAt;
    }
}
