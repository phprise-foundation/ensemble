<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Contract\Kanban\BoardColumnRepository;
use App\Domain\Kanban\BoardColumn;
use App\Domain\Kanban\BoardColumnId;
use App\Domain\Kanban\BoardId;
use App\Domain\Tenancy\WorkspaceId;
use App\Infrastructure\Persistence\Doctrine\Entity\BoardColumnRecord;
use App\Infrastructure\Persistence\Doctrine\Mapper\BoardColumnRecordMapper;
use Doctrine\ORM\EntityManagerInterface;
use Override;

final readonly class DoctrineBoardColumnRepository implements BoardColumnRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BoardColumnRecordMapper $mapper,
    ) {
    }

    #[Override]
    public function add(BoardColumn $column): void
    {
        $this->entityManager->persist($this->mapper->newRecord($column));
        $this->entityManager->flush();
    }

    #[Override]
    public function update(BoardColumn $column): void
    {
        $record = $this->entityManager->find(BoardColumnRecord::class, $column->id()->value());
        if ($record === null) {
            return;
        }

        $this->mapper->sync($record, $column);
        $this->entityManager->flush();
    }

    #[Override]
    public function delete(BoardColumn $column): void
    {
        $record = $this->entityManager->find(BoardColumnRecord::class, $column->id()->value());
        if ($record === null) {
            return;
        }

        $this->entityManager->remove($record);
        $this->entityManager->flush();
    }

    #[Override]
    public function find(WorkspaceId $workspaceId, BoardColumnId $id): ?BoardColumn
    {
        /** @var BoardColumnRecord|null $record */
        $record = $this->entityManager
            ->createQueryBuilder()
            ->select('c')
            ->from(BoardColumnRecord::class, 'c')
            ->where('c.id = :id')
            ->andWhere('c.workspaceId = :workspaceId')
            ->setParameter('id', $id->value())
            ->setParameter('workspaceId', $workspaceId->value())
            ->getQuery()
            ->getOneOrNullResult();

        return $record === null ? null : $this->mapper->toDomain($record);
    }

    #[Override]
    public function forBoard(WorkspaceId $workspaceId, BoardId $boardId): array
    {
        /** @var list<BoardColumnRecord> $records */
        $records = $this->entityManager
            ->createQueryBuilder()
            ->select('c')
            ->from(BoardColumnRecord::class, 'c')
            ->where('c.boardId = :boardId')
            ->andWhere('c.workspaceId = :workspaceId')
            ->setParameter('boardId', $boardId->value())
            ->setParameter('workspaceId', $workspaceId->value())
            ->orderBy('c.position', 'ASC')
            ->addOrderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();

        return array_map(fn(BoardColumnRecord $record): BoardColumn => $this->mapper->toDomain($record), $records);
    }

    #[Override]
    public function countForBoard(BoardId $boardId): int
    {
        $count = $this->entityManager
            ->createQueryBuilder()
            ->select('COUNT(c.id)')
            ->from(BoardColumnRecord::class, 'c')
            ->where('c.boardId = :boardId')
            ->setParameter('boardId', $boardId->value())
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count;
    }
}
