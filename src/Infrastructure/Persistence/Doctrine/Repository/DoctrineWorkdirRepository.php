<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Contract\Kanban\WorkdirRepository;
use App\Domain\Kanban\IssueId;
use App\Domain\Kanban\Workdir;
use App\Domain\Kanban\WorkdirId;
use App\Domain\Tenancy\WorkspaceId;
use App\Infrastructure\Persistence\Doctrine\Entity\WorkdirRecord;
use App\Infrastructure\Persistence\Doctrine\Mapper\WorkdirRecordMapper;
use Doctrine\ORM\EntityManagerInterface;
use Override;

final readonly class DoctrineWorkdirRepository implements WorkdirRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private WorkdirRecordMapper $mapper,
    ) {
    }

    #[Override]
    public function add(Workdir $workdir): void
    {
        $this->entityManager->persist($this->mapper->newRecord($workdir));
        $this->entityManager->flush();
    }

    #[Override]
    public function update(Workdir $workdir): void
    {
        $record = $this->entityManager->find(WorkdirRecord::class, $workdir->id()->value());
        if ($record === null) {
            return;
        }

        $this->mapper->sync($record, $workdir);
        $this->entityManager->flush();
    }

    #[Override]
    public function find(WorkspaceId $workspaceId, WorkdirId $id): ?Workdir
    {
        /** @var WorkdirRecord|null $record */
        $record = $this->entityManager
            ->createQueryBuilder()
            ->select('w')
            ->from(WorkdirRecord::class, 'w')
            ->where('w.id = :id')
            ->andWhere('w.workspaceId = :workspaceId')
            ->setParameter('id', $id->value())
            ->setParameter('workspaceId', $workspaceId->value())
            ->getQuery()
            ->getOneOrNullResult();

        return $record === null ? null : $this->mapper->toDomain($record);
    }

    #[Override]
    public function findMany(WorkspaceId $workspaceId, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        /** @var list<WorkdirRecord> $records */
        $records = $this->entityManager
            ->createQueryBuilder()
            ->select('w')
            ->from(WorkdirRecord::class, 'w')
            ->where('w.id IN (:ids)')
            ->andWhere('w.workspaceId = :workspaceId')
            ->setParameter('ids', array_map(fn(WorkdirId $id): string => $id->value(), $ids))
            ->setParameter('workspaceId', $workspaceId->value())
            ->getQuery()
            ->getResult();

        return array_map(fn(WorkdirRecord $record): Workdir => $this->mapper->toDomain($record), $records);
    }

    #[Override]
    public function findByIssue(WorkspaceId $workspaceId, IssueId $ownerIssueId): ?Workdir
    {
        /** @var WorkdirRecord|null $record */
        $record = $this->entityManager
            ->createQueryBuilder()
            ->select('w')
            ->from(WorkdirRecord::class, 'w')
            ->where('w.ownerIssueId = :ownerIssueId')
            ->andWhere('w.workspaceId = :workspaceId')
            ->setParameter('ownerIssueId', $ownerIssueId->value())
            ->setParameter('workspaceId', $workspaceId->value())
            ->getQuery()
            ->getOneOrNullResult();

        return $record === null ? null : $this->mapper->toDomain($record);
    }
}
