<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Contract\Kanban\IssueRepository;
use App\Domain\Kanban\Issue;
use App\Domain\Kanban\IssueKey;
use App\Domain\Tenancy\WorkspaceId;
use App\Infrastructure\Persistence\Doctrine\Entity\IssueRecord;
use App\Infrastructure\Persistence\Doctrine\Mapper\IssueRecordMapper;
use Doctrine\ORM\EntityManagerInterface;
use Override;

final readonly class DoctrineIssueRepository implements IssueRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private IssueRecordMapper $mapper,
    ) {
    }

    #[Override]
    public function add(Issue $issue): void
    {
        $this->entityManager->persist($this->mapper->newRecord($issue));
        $this->entityManager->flush();
    }

    #[Override]
    public function update(Issue $issue): void
    {
        $record = $this->entityManager->find(IssueRecord::class, $issue->id()->value());
        if ($record === null) {
            return;
        }

        $this->mapper->sync($record, $issue);
        $this->entityManager->flush();
    }

    #[Override]
    public function find(WorkspaceId $workspaceId, IssueKey $key): ?Issue
    {
        /** @var IssueRecord|null $record */
        $record = $this->entityManager
            ->createQueryBuilder()
            ->select('i')
            ->from(IssueRecord::class, 'i')
            ->where('i.key = :key')
            ->andWhere('i.workspaceId = :workspaceId')
            ->setParameter('key', $key->value())
            ->setParameter('workspaceId', $workspaceId->value())
            ->getQuery()
            ->getOneOrNullResult();

        return $record === null ? null : $this->mapper->toDomain($record);
    }

    #[Override]
    public function forWorkspace(WorkspaceId $workspaceId): array
    {
        /** @var list<IssueRecord> $records */
        $records = $this->entityManager
            ->createQueryBuilder()
            ->select('i')
            ->from(IssueRecord::class, 'i')
            ->where('i.workspaceId = :workspaceId')
            ->setParameter('workspaceId', $workspaceId->value())
            ->orderBy('i.createdAt', 'ASC')
            ->getQuery()
            ->getResult();

        return array_map(fn(IssueRecord $record): Issue => $this->mapper->toDomain($record), $records);
    }
}
