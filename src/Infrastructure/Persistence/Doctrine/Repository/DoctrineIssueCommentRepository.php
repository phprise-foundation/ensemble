<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Contract\Kanban\IssueCommentRepository;
use App\Domain\Kanban\IssueComment;
use App\Domain\Kanban\IssueId;
use App\Domain\Tenancy\WorkspaceId;
use App\Infrastructure\Persistence\Doctrine\Entity\IssueCommentRecord;
use App\Infrastructure\Persistence\Doctrine\Mapper\IssueCommentRecordMapper;
use Doctrine\ORM\EntityManagerInterface;
use Override;

final readonly class DoctrineIssueCommentRepository implements IssueCommentRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private IssueCommentRecordMapper $mapper,
    ) {
    }

    #[Override]
    public function add(IssueComment $comment): void
    {
        $this->entityManager->persist($this->mapper->newRecord($comment));
        $this->entityManager->flush();
    }

    #[Override]
    public function forIssue(WorkspaceId $workspaceId, IssueId $issueId): array
    {
        /** @var list<IssueCommentRecord> $records */
        $records = $this->entityManager
            ->createQueryBuilder()
            ->select('c')
            ->from(IssueCommentRecord::class, 'c')
            ->where('c.issueId = :issueId')
            ->andWhere('c.workspaceId = :workspaceId')
            ->setParameter('issueId', $issueId->value())
            ->setParameter('workspaceId', $workspaceId->value())
            ->orderBy('c.createdAt', 'ASC')
            ->getQuery()
            ->getResult();

        return array_map(fn(IssueCommentRecord $record): IssueComment => $this->mapper->toDomain($record), $records);
    }
}
