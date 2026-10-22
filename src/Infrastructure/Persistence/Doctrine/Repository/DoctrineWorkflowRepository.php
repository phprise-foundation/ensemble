<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Contract\Kanban\WorkflowRepository;
use App\Domain\Kanban\Slug;
use App\Domain\Kanban\Workflow;
use App\Domain\Kanban\WorkflowId;
use App\Domain\Tenancy\WorkspaceId;
use App\Infrastructure\Persistence\Doctrine\Entity\WorkflowRecord;
use App\Infrastructure\Persistence\Doctrine\Mapper\WorkflowRecordMapper;
use Doctrine\ORM\EntityManagerInterface;
use Override;

final readonly class DoctrineWorkflowRepository implements WorkflowRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private WorkflowRecordMapper $mapper,
    ) {
    }

    #[Override]
    public function add(Workflow $workflow): void
    {
        $this->entityManager->persist($this->mapper->newRecord($workflow));
        $this->entityManager->flush();
    }

    #[Override]
    public function update(Workflow $workflow): void
    {
        $record = $this->entityManager->find(WorkflowRecord::class, $workflow->id()->value());
        if ($record === null) {
            return;
        }

        $this->mapper->sync($record, $workflow);
        $this->entityManager->flush();
    }

    #[Override]
    public function delete(Workflow $workflow): void
    {
        $record = $this->entityManager->find(WorkflowRecord::class, $workflow->id()->value());
        if ($record === null) {
            return;
        }

        $this->entityManager->remove($record);
        $this->entityManager->flush();
    }

    #[Override]
    public function find(WorkspaceId $workspaceId, WorkflowId $id): ?Workflow
    {
        /** @var WorkflowRecord|null $record */
        $record = $this->entityManager
            ->createQueryBuilder()
            ->select('w')
            ->from(WorkflowRecord::class, 'w')
            ->where('w.id = :id')
            ->andWhere('w.workspaceId = :workspaceId')
            ->setParameter('id', $id->value())
            ->setParameter('workspaceId', $workspaceId->value())
            ->getQuery()
            ->getOneOrNullResult();

        return $record instanceof WorkflowRecord ? $this->mapper->toDomain($record) : null;
    }

    #[Override]
    public function findByKey(WorkspaceId $workspaceId, Slug $key): ?Workflow
    {
        /** @var WorkflowRecord|null $record */
        $record = $this->entityManager
            ->createQueryBuilder()
            ->select('w')
            ->from(WorkflowRecord::class, 'w')
            ->where('w.key = :key')
            ->andWhere('w.workspaceId = :workspaceId')
            ->setParameter('key', $key->value())
            ->setParameter('workspaceId', $workspaceId->value())
            ->getQuery()
            ->getOneOrNullResult();

        return $record instanceof WorkflowRecord ? $this->mapper->toDomain($record) : null;
    }

    #[Override]
    public function forWorkspace(WorkspaceId $workspaceId): array
    {
        /** @var list<WorkflowRecord> $records */
        $records = $this->entityManager
            ->createQueryBuilder()
            ->select('w')
            ->from(WorkflowRecord::class, 'w')
            ->where('w.workspaceId = :workspaceId')
            ->setParameter('workspaceId', $workspaceId->value())
            ->orderBy('w.name', 'ASC')
            ->getQuery()
            ->getResult();

        return array_map(fn(WorkflowRecord $record): Workflow => $this->mapper->toDomain($record), $records);
    }
}
