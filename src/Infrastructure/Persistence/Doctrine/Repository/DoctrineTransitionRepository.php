<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Contract\Kanban\TransitionRepository;
use App\Domain\Kanban\Slug;
use App\Domain\Kanban\StatusId;
use App\Domain\Kanban\Transition;
use App\Domain\Kanban\TransitionActor;
use App\Domain\Kanban\TransitionId;
use App\Domain\Kanban\WorkflowId;
use App\Domain\Tenancy\WorkspaceId;
use App\Infrastructure\Persistence\Doctrine\Entity\TransitionRecord;
use App\Infrastructure\Persistence\Doctrine\Mapper\TransitionRecordMapper;
use Doctrine\ORM\EntityManagerInterface;
use Override;

final readonly class DoctrineTransitionRepository implements TransitionRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TransitionRecordMapper $mapper,
    ) {
    }

    #[Override]
    public function add(Transition $transition): void
    {
        $this->entityManager->persist($this->mapper->newRecord($transition));
        $this->entityManager->flush();
    }

    #[Override]
    public function update(Transition $transition): void
    {
        $record = $this->entityManager->find(TransitionRecord::class, $transition->id()->value());
        if ($record === null) {
            return;
        }

        $this->mapper->sync($record, $transition);
        $this->entityManager->flush();
    }

    #[Override]
    public function delete(Transition $transition): void
    {
        $record = $this->entityManager->find(TransitionRecord::class, $transition->id()->value());
        if ($record === null) {
            return;
        }

        $this->entityManager->remove($record);
        $this->entityManager->flush();
    }

    #[Override]
    public function find(WorkspaceId $workspaceId, TransitionId $id): ?Transition
    {
        /** @var TransitionRecord|null $record */
        $record = $this->entityManager
            ->createQueryBuilder()
            ->select('t')
            ->from(TransitionRecord::class, 't')
            ->where('t.id = :id')
            ->andWhere('t.workspaceId = :workspaceId')
            ->setParameter('id', $id->value())
            ->setParameter('workspaceId', $workspaceId->value())
            ->getQuery()
            ->getOneOrNullResult();

        return $record === null ? null : $this->mapper->toDomain($record);
    }

    #[Override]
    public function forWorkflow(WorkspaceId $workspaceId, WorkflowId $workflowId): array
    {
        /** @var list<TransitionRecord> $records */
        $records = $this->entityManager
            ->createQueryBuilder()
            ->select('t')
            ->from(TransitionRecord::class, 't')
            ->where('t.workflowId = :workflowId')
            ->andWhere('t.workspaceId = :workspaceId')
            ->setParameter('workflowId', $workflowId->value())
            ->setParameter('workspaceId', $workspaceId->value())
            ->orderBy('t.createdAt', 'ASC')
            ->getQuery()
            ->getResult();

        return array_map(fn(TransitionRecord $record): Transition => $this->mapper->toDomain($record), $records);
    }

    #[Override]
    public function findExisting(
        WorkflowId $workflowId,
        StatusId $fromStatusId,
        StatusId $toStatusId,
        TransitionActor $actor,
        ?Slug $outcome,
    ): ?Transition {
        $query = $this->entityManager
            ->createQueryBuilder()
            ->select('t')
            ->from(TransitionRecord::class, 't')
            ->where('t.workflowId = :workflowId')
            ->andWhere('t.fromStatusId = :fromStatusId')
            ->andWhere('t.toStatusId = :toStatusId')
            ->andWhere('t.actor = :actor')
            ->setParameter('workflowId', $workflowId->value())
            ->setParameter('fromStatusId', $fromStatusId->value())
            ->setParameter('toStatusId', $toStatusId->value())
            ->setParameter('actor', $actor->value);
        if ($outcome === null) {
            $query->andWhere('t.outcome IS NULL');
        } else {
            $query->andWhere('t.outcome = :outcome')->setParameter('outcome', $outcome->value());
        }

        /** @var TransitionRecord|null $record */
        $record = $query->getQuery()->getOneOrNullResult();

        return $record === null ? null : $this->mapper->toDomain($record);
    }

    #[Override]
    public function countForStatus(StatusId $statusId): int
    {
        $count = $this->entityManager
            ->createQueryBuilder()
            ->select('COUNT(t.id)')
            ->from(TransitionRecord::class, 't')
            ->where('t.fromStatusId = :statusId OR t.toStatusId = :statusId')
            ->setParameter('statusId', $statusId->value())
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count;
    }
}
