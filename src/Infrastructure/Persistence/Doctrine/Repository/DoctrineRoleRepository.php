<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Contract\Team\RoleRepository;
use App\Domain\Team\Role;
use App\Domain\Team\RoleId;
use App\Domain\Team\RoleIds;
use App\Domain\Team\TeamId;
use App\Domain\Tenancy\WorkspaceId;
use App\Infrastructure\Persistence\Doctrine\Entity\RoleRecord;
use App\Infrastructure\Persistence\Doctrine\Entity\StatusRoleRecord;
use App\Infrastructure\Persistence\Doctrine\Mapper\RoleRecordMapper;
use Doctrine\ORM\EntityManagerInterface;
use Override;

final readonly class DoctrineRoleRepository implements RoleRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private RoleRecordMapper $mapper,
    ) {
    }

    #[Override]
    public function add(Role $role): void
    {
        $this->entityManager->persist($this->mapper->newRecord($role));
        $this->entityManager->flush();
    }

    #[Override]
    public function update(Role $role): void
    {
        $record = $this->entityManager->find(RoleRecord::class, $role->id()->value());
        if ($record === null) {
            return;
        }

        $this->mapper->sync($record, $role);
        $this->entityManager->flush();
    }

    #[Override]
    public function delete(Role $role): void
    {
        $record = $this->entityManager->find(RoleRecord::class, $role->id()->value());
        if ($record === null) {
            return;
        }

        $this->entityManager->remove($record);
        $this->entityManager->flush();
    }

    #[Override]
    public function find(WorkspaceId $workspaceId, RoleId $id): ?Role
    {
        /** @var RoleRecord|null $record */
        $record = $this->entityManager
            ->createQueryBuilder()
            ->select('r')
            ->from(RoleRecord::class, 'r')
            ->where('r.id = :id')
            ->andWhere('r.workspaceId = :workspaceId')
            ->setParameter('id', $id->value())
            ->setParameter('workspaceId', $workspaceId->value())
            ->getQuery()
            ->getOneOrNullResult();

        return $record === null ? null : $this->mapper->toDomain($record);
    }

    #[Override]
    public function forTeam(WorkspaceId $workspaceId, TeamId $teamId): array
    {
        /** @var list<RoleRecord> $records */
        $records = $this->entityManager
            ->createQueryBuilder()
            ->select('r')
            ->from(RoleRecord::class, 'r')
            ->where('r.teamId = :teamId')
            ->andWhere('r.workspaceId = :workspaceId')
            ->setParameter('teamId', $teamId->value())
            ->setParameter('workspaceId', $workspaceId->value())
            ->orderBy('r.name', 'ASC')
            ->getQuery()
            ->getResult();

        return array_map(fn(RoleRecord $record): Role => $this->mapper->toDomain($record), $records);
    }

    #[Override]
    public function findMany(WorkspaceId $workspaceId, RoleIds $ids): RoleIds
    {
        if ($ids->isEmpty()) {
            return new RoleIds();
        }

        /** @var list<RoleRecord> $records */
        $records = $this->entityManager
            ->createQueryBuilder()
            ->select('r')
            ->from(RoleRecord::class, 'r')
            ->where('r.id IN (:ids)')
            ->andWhere('r.workspaceId = :workspaceId')
            ->setParameter('ids', $ids->values())
            ->setParameter('workspaceId', $workspaceId->value())
            ->getQuery()
            ->getResult();

        return new RoleIds(array_map(
            fn(RoleRecord $record): RoleId => new RoleId($record->id()),
            $records,
        ));
    }

    #[Override]
    public function countForTeam(TeamId $teamId): int
    {
        $count = $this->entityManager
            ->createQueryBuilder()
            ->select('COUNT(r.id)')
            ->from(RoleRecord::class, 'r')
            ->where('r.teamId = :teamId')
            ->setParameter('teamId', $teamId->value())
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count;
    }

    #[Override]
    public function assignedToStatus(RoleId $id): bool
    {
        $count = $this->entityManager
            ->createQueryBuilder()
            ->select('COUNT(sr.roleId)')
            ->from(StatusRoleRecord::class, 'sr')
            ->where('sr.roleId = :roleId')
            ->setParameter('roleId', $id->value())
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count > 0;
    }
}
