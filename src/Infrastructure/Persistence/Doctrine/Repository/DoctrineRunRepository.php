<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Contract\Runner\RunRepository;
use App\Domain\Kanban\IssueId;
use App\Domain\Runner\Run;
use App\Domain\Team\AgentId;
use App\Domain\Tenancy\WorkspaceId;
use App\Infrastructure\Persistence\Doctrine\Entity\RunRecord;
use App\Infrastructure\Persistence\Doctrine\Mapper\RunRecordMapper;
use Doctrine\ORM\EntityManagerInterface;
use Override;

final readonly class DoctrineRunRepository implements RunRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private RunRecordMapper $mapper,
    ) {
    }

    #[Override]
    public function add(Run $run): void
    {
        $this->entityManager->persist($this->mapper->newRecord($run));
        $this->entityManager->flush();
    }

    #[Override]
    public function update(Run $run): void
    {
        $record = $this->entityManager->find(RunRecord::class, $run->id()->value());
        if ($record === null) {
            return;
        }

        $this->mapper->sync($record, $run);
        $this->entityManager->flush();
    }

    #[Override]
    public function activeForIssue(IssueId $issueId): ?Run
    {
        /** @var RunRecord|null $record */
        $record = $this->entityManager
            ->createQueryBuilder()
            ->select('r')
            ->from(RunRecord::class, 'r')
            ->where('r.issueId = :issueId')
            ->andWhere('r.state IN (:states)')
            ->setParameter('issueId', $issueId->value())
            ->setParameter('states', ['claimed', 'running'])
            ->orderBy('r.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $record === null ? null : $this->mapper->toDomain($record);
    }

    #[Override]
    public function latestFor(AgentId $agentId, IssueId $issueId): ?Run
    {
        /** @var RunRecord|null $record */
        $record = $this->entityManager
            ->createQueryBuilder()
            ->select('r')
            ->from(RunRecord::class, 'r')
            ->where('r.issueId = :issueId')
            ->andWhere('r.agentId = :agentId')
            ->setParameter('issueId', $issueId->value())
            ->setParameter('agentId', $agentId->value())
            ->orderBy('r.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $record === null ? null : $this->mapper->toDomain($record);
    }

    #[Override]
    public function withExpiredLeases(): array
    {
        /** @var list<RunRecord> $records */
        $records = $this->entityManager
            ->createQueryBuilder()
            ->select('r')
            ->from(RunRecord::class, 'r')
            ->where('r.state IN (:states)')
            ->andWhere('r.leaseExpiresAt IS NOT NULL')
            ->andWhere('r.leaseExpiresAt <= :now')
            ->setParameter('states', ['claimed', 'running'])
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getResult();

        return array_map(fn(RunRecord $record): Run => $this->mapper->toDomain($record), $records);
    }

    #[Override]
    public function forIssue(WorkspaceId $workspaceId, IssueId $issueId): array
    {
        /** @var list<RunRecord> $records */
        $records = $this->entityManager
            ->createQueryBuilder()
            ->select('r')
            ->from(RunRecord::class, 'r')
            ->where('r.workspaceId = :workspaceId')
            ->andWhere('r.issueId = :issueId')
            ->setParameter('workspaceId', $workspaceId->value())
            ->setParameter('issueId', $issueId->value())
            ->orderBy('r.createdAt', 'ASC')
            ->getQuery()
            ->getResult();

        return array_map(fn(RunRecord $record): Run => $this->mapper->toDomain($record), $records);
    }
}
