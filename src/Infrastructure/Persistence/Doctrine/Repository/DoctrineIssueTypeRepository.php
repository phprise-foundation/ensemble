<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Contract\Kanban\IssueTypeRepository;
use App\Domain\Kanban\IssueType;
use App\Domain\Kanban\IssueTypeId;
use App\Domain\Kanban\Slug;
use App\Domain\Kanban\WorkflowId;
use App\Domain\Tenancy\WorkspaceId;
use App\Infrastructure\Persistence\Doctrine\Entity\IssueTypeRecord;
use App\Infrastructure\Persistence\Doctrine\Mapper\IssueTypeRecordMapper;
use Doctrine\ORM\EntityManagerInterface;
use Override;

final readonly class DoctrineIssueTypeRepository implements IssueTypeRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private IssueTypeRecordMapper $mapper,
    ) {
    }

    #[Override]
    public function add(IssueType $issueType): void
    {
        $this->entityManager->persist($this->mapper->newRecord($issueType));
        $this->entityManager->flush();
    }

    #[Override]
    public function update(IssueType $issueType): void
    {
        $record = $this->entityManager->find(IssueTypeRecord::class, $issueType->id()->value());
        if ($record === null) {
            return;
        }

        $this->mapper->sync($record, $issueType);
        $this->entityManager->flush();
    }

    #[Override]
    public function delete(IssueType $issueType): void
    {
        $record = $this->entityManager->find(IssueTypeRecord::class, $issueType->id()->value());
        if ($record === null) {
            return;
        }

        $this->entityManager->remove($record);
        $this->entityManager->flush();
    }

    #[Override]
    public function find(WorkspaceId $workspaceId, IssueTypeId $id): ?IssueType
    {
        /** @var IssueTypeRecord|null $record */
        $record = $this->entityManager
            ->createQueryBuilder()
            ->select('t')
            ->from(IssueTypeRecord::class, 't')
            ->where('t.id = :id')
            ->andWhere('t.workspaceId = :workspaceId')
            ->setParameter('id', $id->value())
            ->setParameter('workspaceId', $workspaceId->value())
            ->getQuery()
            ->getOneOrNullResult();

        return $record instanceof IssueTypeRecord ? $this->mapper->toDomain($record) : null;
    }

    #[Override]
    public function findByKey(WorkflowId $workflowId, Slug $key): ?IssueType
    {
        /** @var IssueTypeRecord|null $record */
        $record = $this->entityManager
            ->createQueryBuilder()
            ->select('t')
            ->from(IssueTypeRecord::class, 't')
            ->where('t.workflowId = :workflowId')
            ->andWhere('t.key = :key')
            ->setParameter('workflowId', $workflowId->value())
            ->setParameter('key', $key->value())
            ->getQuery()
            ->getOneOrNullResult();

        return $record instanceof IssueTypeRecord ? $this->mapper->toDomain($record) : null;
    }

    #[Override]
    public function forWorkflow(WorkspaceId $workspaceId, WorkflowId $workflowId): array
    {
        /** @var list<IssueTypeRecord> $records */
        $records = $this->entityManager
            ->createQueryBuilder()
            ->select('t')
            ->from(IssueTypeRecord::class, 't')
            ->where('t.workflowId = :workflowId')
            ->andWhere('t.workspaceId = :workspaceId')
            ->setParameter('workflowId', $workflowId->value())
            ->setParameter('workspaceId', $workspaceId->value())
            ->orderBy('t.position', 'ASC')
            ->addOrderBy('t.name', 'ASC')
            ->getQuery()
            ->getResult();

        return array_map(fn(IssueTypeRecord $record): IssueType => $this->mapper->toDomain($record), $records);
    }

    #[Override]
    public function countForWorkflow(WorkflowId $workflowId): int
    {
        $count = $this->entityManager
            ->createQueryBuilder()
            ->select('COUNT(t.id)')
            ->from(IssueTypeRecord::class, 't')
            ->where('t.workflowId = :workflowId')
            ->setParameter('workflowId', $workflowId->value())
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count;
    }
}
