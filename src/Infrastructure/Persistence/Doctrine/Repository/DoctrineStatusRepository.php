<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Contract\Kanban\StatusRepository;
use App\Domain\Kanban\IssueTypeId;
use App\Domain\Kanban\Slug;
use App\Domain\Kanban\Status;
use App\Domain\Kanban\StatusId;
use App\Domain\Kanban\StatusType;
use App\Domain\Kanban\WorkflowId;
use App\Domain\Tenancy\WorkspaceId;
use App\Infrastructure\Persistence\Doctrine\Entity\StatusRecord;
use App\Infrastructure\Persistence\Doctrine\Mapper\StatusRecordMapper;
use Doctrine\ORM\EntityManagerInterface;
use Override;

final readonly class DoctrineStatusRepository implements StatusRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private StatusRecordMapper $mapper,
    ) {
    }

    #[Override]
    public function add(Status $status): void
    {
        $this->entityManager->persist($this->mapper->newRecord($status));
        $this->entityManager->flush();
    }

    #[Override]
    public function update(Status $status): void
    {
        $record = $this->entityManager->find(StatusRecord::class, $status->id()->value());
        if ($record === null) {
            return;
        }

        $this->mapper->sync($record, $status);
        $this->entityManager->flush();
    }

    #[Override]
    public function delete(Status $status): void
    {
        $record = $this->entityManager->find(StatusRecord::class, $status->id()->value());
        if ($record === null) {
            return;
        }

        $this->entityManager->remove($record);
        $this->entityManager->flush();
    }

    #[Override]
    public function find(WorkspaceId $workspaceId, StatusId $id): ?Status
    {
        /** @var StatusRecord|null $record */
        $record = $this->entityManager
            ->createQueryBuilder()
            ->select('s')
            ->from(StatusRecord::class, 's')
            ->where('s.id = :id')
            ->andWhere('s.workspaceId = :workspaceId')
            ->setParameter('id', $id->value())
            ->setParameter('workspaceId', $workspaceId->value())
            ->getQuery()
            ->getOneOrNullResult();

        return $record === null ? null : $this->mapper->toDomain($record);
    }

    #[Override]
    public function findByKey(IssueTypeId $issueTypeId, Slug $key): ?Status
    {
        /** @var StatusRecord|null $record */
        $record = $this->entityManager
            ->createQueryBuilder()
            ->select('s')
            ->from(StatusRecord::class, 's')
            ->where('s.issueTypeId = :issueTypeId')
            ->andWhere('s.key = :key')
            ->setParameter('issueTypeId', $issueTypeId->value())
            ->setParameter('key', $key->value())
            ->getQuery()
            ->getOneOrNullResult();

        return $record === null ? null : $this->mapper->toDomain($record);
    }

    #[Override]
    public function forIssueType(WorkspaceId $workspaceId, IssueTypeId $issueTypeId): array
    {
        /** @var list<StatusRecord> $records */
        $records = $this->entityManager
            ->createQueryBuilder()
            ->select('s')
            ->from(StatusRecord::class, 's')
            ->where('s.issueTypeId = :issueTypeId')
            ->andWhere('s.workspaceId = :workspaceId')
            ->setParameter('issueTypeId', $issueTypeId->value())
            ->setParameter('workspaceId', $workspaceId->value())
            ->orderBy('s.position', 'ASC')
            ->addOrderBy('s.name', 'ASC')
            ->getQuery()
            ->getResult();

        return array_map(fn(StatusRecord $record): Status => $this->mapper->toDomain($record), $records);
    }

    #[Override]
    public function forWorkflow(WorkspaceId $workspaceId, WorkflowId $workflowId): array
    {
        /** @var list<StatusRecord> $records */
        $records = $this->entityManager
            ->createQueryBuilder()
            ->select('s')
            ->from(StatusRecord::class, 's')
            ->where('s.workflowId = :workflowId')
            ->andWhere('s.workspaceId = :workspaceId')
            ->setParameter('workflowId', $workflowId->value())
            ->setParameter('workspaceId', $workspaceId->value())
            ->orderBy('s.position', 'ASC')
            ->addOrderBy('s.name', 'ASC')
            ->getQuery()
            ->getResult();

        return array_map(fn(StatusRecord $record): Status => $this->mapper->toDomain($record), $records);
    }

    #[Override]
    public function countForIssueType(IssueTypeId $issueTypeId): int
    {
        $count = $this->entityManager
            ->createQueryBuilder()
            ->select('COUNT(s.id)')
            ->from(StatusRecord::class, 's')
            ->where('s.issueTypeId = :issueTypeId')
            ->setParameter('issueTypeId', $issueTypeId->value())
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count;
    }

    #[Override]
    public function countByType(IssueTypeId $issueTypeId, StatusType $type): int
    {
        $count = $this->entityManager
            ->createQueryBuilder()
            ->select('COUNT(s.id)')
            ->from(StatusRecord::class, 's')
            ->where('s.issueTypeId = :issueTypeId')
            ->andWhere('s.type = :type')
            ->setParameter('issueTypeId', $issueTypeId->value())
            ->setParameter('type', $type->value)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count;
    }
}
