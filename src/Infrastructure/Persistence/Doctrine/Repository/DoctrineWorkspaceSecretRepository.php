<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Contract\Security\WorkspaceSecretRepository;
use App\Domain\Security\WorkspaceSecret;
use App\Domain\Security\WorkspaceSecretId;
use App\Domain\Tenancy\WorkspaceId;
use App\Infrastructure\Persistence\Doctrine\Entity\WorkspaceSecretRecord;
use App\Infrastructure\Persistence\Doctrine\Mapper\WorkspaceSecretRecordMapper;
use Doctrine\ORM\EntityManagerInterface;
use Override;

final readonly class DoctrineWorkspaceSecretRepository implements WorkspaceSecretRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private WorkspaceSecretRecordMapper $mapper,
    ) {
    }

    #[Override]
    public function add(WorkspaceSecret $secret): void
    {
        $this->entityManager->persist($this->mapper->newRecord($secret));
        $this->entityManager->flush();
    }

    #[Override]
    public function update(WorkspaceSecret $secret): void
    {
        $record = $this->entityManager->find(WorkspaceSecretRecord::class, $secret->id()->value());
        if ($record === null) {
            return;
        }

        $this->mapper->sync($record, $secret);
        $this->entityManager->flush();
    }

    #[Override]
    public function delete(WorkspaceSecret $secret): void
    {
        $record = $this->entityManager->find(WorkspaceSecretRecord::class, $secret->id()->value());
        if ($record === null) {
            return;
        }

        $this->entityManager->remove($record);
        $this->entityManager->flush();
    }

    #[Override]
    public function find(WorkspaceId $workspaceId, WorkspaceSecretId $id): ?WorkspaceSecret
    {
        /** @var WorkspaceSecretRecord|null $record */
        $record = $this->entityManager
            ->createQueryBuilder()
            ->select('s')
            ->from(WorkspaceSecretRecord::class, 's')
            ->where('s.id = :id')
            ->andWhere('s.workspaceId = :workspaceId')
            ->setParameter('id', $id->value())
            ->setParameter('workspaceId', $workspaceId->value())
            ->getQuery()
            ->getOneOrNullResult();

        return $record === null ? null : $this->mapper->toDomain($record);
    }

    #[Override]
    public function findByName(WorkspaceId $workspaceId, string $name): ?WorkspaceSecret
    {
        /** @var WorkspaceSecretRecord|null $record */
        $record = $this->entityManager
            ->createQueryBuilder()
            ->select('s')
            ->from(WorkspaceSecretRecord::class, 's')
            ->where('s.name = :name')
            ->andWhere('s.workspaceId = :workspaceId')
            ->setParameter('name', $name)
            ->setParameter('workspaceId', $workspaceId->value())
            ->getQuery()
            ->getOneOrNullResult();

        return $record === null ? null : $this->mapper->toDomain($record);
    }

    #[Override]
    public function all(WorkspaceId $workspaceId): array
    {
        /** @var list<WorkspaceSecretRecord> $records */
        $records = $this->entityManager
            ->createQueryBuilder()
            ->select('s')
            ->from(WorkspaceSecretRecord::class, 's')
            ->where('s.workspaceId = :workspaceId')
            ->setParameter('workspaceId', $workspaceId->value())
            ->orderBy('s.name', 'ASC')
            ->getQuery()
            ->getResult();

        return array_map(fn(WorkspaceSecretRecord $record): WorkspaceSecret => $this->mapper->toDomain($record), $records);
    }
}
