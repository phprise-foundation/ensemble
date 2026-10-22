<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Contract\Kanban\BoardRepository;
use App\Domain\Kanban\Board;
use App\Domain\Kanban\BoardId;
use App\Domain\Kanban\Slug;
use App\Domain\Tenancy\WorkspaceId;
use App\Infrastructure\Persistence\Doctrine\Entity\BoardRecord;
use App\Infrastructure\Persistence\Doctrine\Mapper\BoardRecordMapper;
use Doctrine\ORM\EntityManagerInterface;
use Override;

final readonly class DoctrineBoardRepository implements BoardRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BoardRecordMapper $mapper,
    ) {
    }

    #[Override]
    public function add(Board $board): void
    {
        $this->entityManager->persist($this->mapper->newRecord($board));
        $this->entityManager->flush();
    }

    #[Override]
    public function update(Board $board): void
    {
        $record = $this->entityManager->find(BoardRecord::class, $board->id()->value());
        if ($record === null) {
            return;
        }

        $this->mapper->sync($record, $board);
        $this->entityManager->flush();
    }

    #[Override]
    public function delete(Board $board): void
    {
        $record = $this->entityManager->find(BoardRecord::class, $board->id()->value());
        if ($record === null) {
            return;
        }

        $this->entityManager->remove($record);
        $this->entityManager->flush();
    }

    #[Override]
    public function find(WorkspaceId $workspaceId, BoardId $id): ?Board
    {
        /** @var BoardRecord|null $record */
        $record = $this->entityManager
            ->createQueryBuilder()
            ->select('b')
            ->from(BoardRecord::class, 'b')
            ->where('b.id = :id')
            ->andWhere('b.workspaceId = :workspaceId')
            ->setParameter('id', $id->value())
            ->setParameter('workspaceId', $workspaceId->value())
            ->getQuery()
            ->getOneOrNullResult();

        return $record === null ? null : $this->mapper->toDomain($record);
    }

    #[Override]
    public function findByKey(WorkspaceId $workspaceId, Slug $key): ?Board
    {
        /** @var BoardRecord|null $record */
        $record = $this->entityManager
            ->createQueryBuilder()
            ->select('b')
            ->from(BoardRecord::class, 'b')
            ->where('b.key = :key')
            ->andWhere('b.workspaceId = :workspaceId')
            ->setParameter('key', $key->value())
            ->setParameter('workspaceId', $workspaceId->value())
            ->getQuery()
            ->getOneOrNullResult();

        return $record === null ? null : $this->mapper->toDomain($record);
    }

    #[Override]
    public function forWorkspace(WorkspaceId $workspaceId): array
    {
        /** @var list<BoardRecord> $records */
        $records = $this->entityManager
            ->createQueryBuilder()
            ->select('b')
            ->from(BoardRecord::class, 'b')
            ->where('b.workspaceId = :workspaceId')
            ->setParameter('workspaceId', $workspaceId->value())
            ->orderBy('b.name', 'ASC')
            ->getQuery()
            ->getResult();

        return array_map(fn(BoardRecord $record): Board => $this->mapper->toDomain($record), $records);
    }
}
