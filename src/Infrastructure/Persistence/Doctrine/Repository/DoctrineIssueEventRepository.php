<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Contract\Kanban\IssueEventRepository;
use App\Domain\Kanban\IssueEvent;
use App\Domain\Kanban\IssueId;
use App\Domain\Tenancy\WorkspaceId;
use App\Infrastructure\Persistence\Doctrine\Entity\IssueEventRecord;
use App\Infrastructure\Persistence\Doctrine\Mapper\IssueEventRecordMapper;
use Doctrine\ORM\EntityManagerInterface;
use Override;

final readonly class DoctrineIssueEventRepository implements IssueEventRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private IssueEventRecordMapper $mapper,
    ) {
    }

    #[Override]
    public function add(IssueEvent $event): void
    {
        $this->entityManager->persist($this->mapper->newRecord($event));
        $this->entityManager->flush();
    }

    #[Override]
    public function forIssue(WorkspaceId $workspaceId, IssueId $issueId): array
    {
        /** @var list<IssueEventRecord> $records */
        $records = $this->entityManager
            ->createQueryBuilder()
            ->select('e')
            ->from(IssueEventRecord::class, 'e')
            ->where('e.issueId = :issueId')
            ->andWhere('e.workspaceId = :workspaceId')
            ->setParameter('issueId', $issueId->value())
            ->setParameter('workspaceId', $workspaceId->value())
            ->orderBy('e.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return array_map(fn(IssueEventRecord $record): IssueEvent => $this->mapper->toDomain($record), $records);
    }
}
