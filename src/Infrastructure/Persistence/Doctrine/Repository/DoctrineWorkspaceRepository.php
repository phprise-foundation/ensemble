<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Contract\Tenancy\WorkspaceRepository;
use App\Domain\Tenancy\UserId;
use App\Domain\Tenancy\Workspace;
use App\Domain\Tenancy\WorkspaceId;
use App\Infrastructure\Persistence\Doctrine\Entity\MembershipRecord;
use App\Infrastructure\Persistence\Doctrine\Entity\WorkspaceRecord;
use App\Infrastructure\Persistence\Doctrine\Mapper\WorkspaceRecordMapper;
use Doctrine\ORM\EntityManagerInterface;
use Override;

final readonly class DoctrineWorkspaceRepository implements WorkspaceRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private WorkspaceRecordMapper $mapper,
    ) {
    }

    #[Override]
    public function add(Workspace $workspace): void
    {
        $this->entityManager->persist($this->mapper->newRecord($workspace));
        $this->entityManager->flush();
    }

    #[Override]
    public function update(Workspace $workspace): void
    {
        $record = $this->entityManager->find(WorkspaceRecord::class, $workspace->id()->value());
        if ($record === null) {
            return;
        }

        $this->mapper->sync($record, $workspace);
        $this->entityManager->flush();
    }

    #[Override]
    public function find(WorkspaceId $id): ?Workspace
    {
        $record = $this->entityManager->find(WorkspaceRecord::class, $id->value());

        return $record === null ? null : $this->mapper->toDomain($record);
    }

    #[Override]
    public function all(): array
    {
        /** @var list<WorkspaceRecord> $records */
        $records = $this->entityManager
            ->createQueryBuilder()
            ->select('w')
            ->from(WorkspaceRecord::class, 'w')
            ->orderBy('w.name', 'ASC')
            ->getQuery()
            ->getResult();

        return array_map(
            fn(WorkspaceRecord $record): Workspace => $this->mapper->toDomain($record),
            $records,
        );
    }

    #[Override]
    public function forUser(UserId $userId): array
    {
        $subQuery = $this->entityManager
            ->createQueryBuilder()
            ->select('m.workspaceId')
            ->from(MembershipRecord::class, 'm')
            ->where('m.userId = :userId')
            ->getDQL();

        $queryBuilder = $this->entityManager->createQueryBuilder();
        /** @var list<WorkspaceRecord> $records */
        $records = $queryBuilder
            ->select('w')
            ->from(WorkspaceRecord::class, 'w')
            ->where($queryBuilder->expr()->in('w.id', $subQuery))
            ->setParameter('userId', $userId->value())
            ->orderBy('w.name', 'ASC')
            ->getQuery()
            ->getResult();

        return array_map(
            fn(WorkspaceRecord $record): Workspace => $this->mapper->toDomain($record),
            $records,
        );
    }

    #[Override]
    public function existsAny(): bool
    {
        $count = $this->entityManager
            ->createQueryBuilder()
            ->select('COUNT(w.id)')
            ->from(WorkspaceRecord::class, 'w')
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count > 0;
    }
}
