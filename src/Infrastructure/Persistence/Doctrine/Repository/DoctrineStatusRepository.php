<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Contract\Kanban\StatusRepository;
use App\Domain\Kanban\IssueTypeId;
use App\Domain\Kanban\Slug;
use App\Domain\Kanban\Status;
use App\Domain\Kanban\StatusId;
use App\Domain\Kanban\StatusType;
use App\Domain\Kanban\WorkflowId;
use App\Domain\Team\RoleId;
use App\Domain\Team\RoleIds;
use App\Domain\Tenancy\WorkspaceId;
use App\Infrastructure\Persistence\Doctrine\Entity\StatusRecord;
use App\Infrastructure\Persistence\Doctrine\Entity\StatusRoleRecord;
use App\Infrastructure\Persistence\Doctrine\Mapper\StatusRecordMapper;
use Doctrine\ORM\EntityManagerInterface;
use Override;

final readonly class DoctrineStatusRepository implements StatusRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private StatusRecordMapper $mapper,
    ) {
    }

    #[Override]
    public function add(Status $status): void
    {
        $this->entityManager->persist($this->mapper->newRecord($status));
        $this->syncRoles($status);
        $this->entityManager->flush();
    }

    #[Override]
    public function update(Status $status): void
    {
        $record = $this->entityManager->find(StatusRecord::class, $status->id()->value());
        if ($record === null) {
            return;
        }

        $this->mapper->sync($record, $status);
        $this->syncRoles($status);
        $this->entityManager->flush();
    }

    #[Override]
    public function delete(Status $status): void
    {
        $record = $this->entityManager->find(StatusRecord::class, $status->id()->value());
        if ($record === null) {
            return;
        }

        $this->deleteRoles($status->id());
        $this->entityManager->remove($record);
        $this->entityManager->flush();
    }

    #[Override]
    public function find(WorkspaceId $workspaceId, StatusId $id): ?Status
    {
        /** @var StatusRecord|null $record */
        $record = $this->entityManager
            ->createQueryBuilder()
            ->select('s')
            ->from(StatusRecord::class, 's')
            ->where('s.id = :id')
            ->andWhere('s.workspaceId = :workspaceId')
            ->setParameter('id', $id->value())
            ->setParameter('workspaceId', $workspaceId->value())
            ->getQuery()
            ->getOneOrNullResult();

        if ($record === null) {
            return null;
        }

        $roles = $this->rolesFor([$record->id()]);

        return $this->mapper->toDomain($record, $roles[$record->id()] ?? new RoleIds());
    }

    #[Override]
    public function findByKey(IssueTypeId $issueTypeId, Slug $key): ?Status
    {
        /** @var StatusRecord|null $record */
        $record = $this->entityManager
            ->createQueryBuilder()
            ->select('s')
            ->from(StatusRecord::class, 's')
            ->where('s.issueTypeId = :issueTypeId')
            ->andWhere('s.key = :key')
            ->setParameter('issueTypeId', $issueTypeId->value())
            ->setParameter('key', $key->value())
            ->getQuery()
            ->getOneOrNullResult();

        if ($record === null) {
            return null;
        }

        $roles = $this->rolesFor([$record->id()]);

        return $this->mapper->toDomain($record, $roles[$record->id()] ?? new RoleIds());
    }

    #[Override]
    public function forIssueType(WorkspaceId $workspaceId, IssueTypeId $issueTypeId): array
    {
        /** @var list<StatusRecord> $records */
        $records = $this->entityManager
            ->createQueryBuilder()
            ->select('s')
            ->from(StatusRecord::class, 's')
            ->where('s.issueTypeId = :issueTypeId')
            ->andWhere('s.workspaceId = :workspaceId')
            ->setParameter('issueTypeId', $issueTypeId->value())
            ->setParameter('workspaceId', $workspaceId->value())
            ->orderBy('s.position', 'ASC')
            ->addOrderBy('s.name', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->domains($records);
    }

    #[Override]
    public function forWorkflow(WorkspaceId $workspaceId, WorkflowId $workflowId): array
    {
        /** @var list<StatusRecord> $records */
        $records = $this->entityManager
            ->createQueryBuilder()
            ->select('s')
            ->from(StatusRecord::class, 's')
            ->where('s.workflowId = :workflowId')
            ->andWhere('s.workspaceId = :workspaceId')
            ->setParameter('workflowId', $workflowId->value())
            ->setParameter('workspaceId', $workspaceId->value())
            ->orderBy('s.position', 'ASC')
            ->addOrderBy('s.name', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->domains($records);
    }

    #[Override]
    public function countForIssueType(IssueTypeId $issueTypeId): int
    {
        $count = $this->entityManager
            ->createQueryBuilder()
            ->select('COUNT(s.id)')
            ->from(StatusRecord::class, 's')
            ->where('s.issueTypeId = :issueTypeId')
            ->setParameter('issueTypeId', $issueTypeId->value())
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count;
    }

    #[Override]
    public function countByType(IssueTypeId $issueTypeId, StatusType $type): int
    {
        $count = $this->entityManager
            ->createQueryBuilder()
            ->select('COUNT(s.id)')
            ->from(StatusRecord::class, 's')
            ->where('s.issueTypeId = :issueTypeId')
            ->andWhere('s.type = :type')
            ->setParameter('issueTypeId', $issueTypeId->value())
            ->setParameter('type', $type->value)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count;
    }

    /**
     * @param list<StatusRecord> $records
     * @return list<Status>
     */
    private function domains(array $records): array
    {
        $roles = $this->rolesFor(array_map(fn(StatusRecord $record): string => $record->id(), $records));

        return array_map(
            fn(StatusRecord $record): Status => $this->mapper->toDomain($record, $roles[$record->id()] ?? new RoleIds()),
            $records,
        );
    }

    #[Override]
    public function ownedByRoles(WorkspaceId $workspaceId, RoleIds $roleIds): array
    {
        $ids = array_map(fn(RoleId $id): string => $id->value(), $roleIds->all());
        if ($ids === []) {
            return [];
        }

        /** @var list<string> $statusIds */
        $statusIds = $this->entityManager
            ->createQueryBuilder()
            ->select('sr.statusId')
            ->from(StatusRoleRecord::class, 'sr')
            ->where('sr.roleId IN (:roleIds)')
            ->setParameter('roleIds', $ids)
            ->getQuery()
            ->getSingleColumnResult();

        if ($statusIds === []) {
            return [];
        }

        /** @var list<StatusRecord> $records */
        $records = $this->entityManager
            ->createQueryBuilder()
            ->select('s')
            ->from(StatusRecord::class, 's')
            ->where('s.workspaceId = :workspaceId')
            ->andWhere('s.id IN (:statusIds)')
            ->setParameter('workspaceId', $workspaceId->value())
            ->setParameter('statusIds', $statusIds)
            ->orderBy('s.name', 'ASC')
            ->getQuery()
            ->getResult();

        $roles = $this->rolesFor(array_map(fn(StatusRecord $record): string => $record->id(), $records));

        return array_map(
            fn(StatusRecord $record): Status => $this->mapper->toDomain($record, $roles[$record->id()] ?? new RoleIds()),
            $records,
        );
    }

    /**
     * @param list<string> $statusIds
     * @return array<string, RoleIds>
     */
    private function rolesFor(array $statusIds): array
    {
        if ($statusIds === []) {
            return [];
        }

        /** @var list<array{statusId: string, roleId: string}> $rows */
        $rows = $this->entityManager
            ->createQueryBuilder()
            ->select('sr.statusId AS statusId', 'sr.roleId AS roleId')
            ->from(StatusRoleRecord::class, 'sr')
            ->where('sr.statusId IN (:statusIds)')
            ->setParameter('statusIds', $statusIds)
            ->getQuery()
            ->getArrayResult();

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[$row['statusId']][] = new RoleId($row['roleId']);
        }

        $roles = [];
        foreach ($grouped as $statusId => $roleIds) {
            $roles[$statusId] = new RoleIds($roleIds);
        }

        return $roles;
    }

    private function syncRoles(Status $status): void
    {
        $this->deleteRoles($status->id());
        foreach ($status->roleIds() as $roleId) {
            $this->entityManager->persist(new StatusRoleRecord($status->id()->value(), $roleId->value()));
        }
    }

    private function deleteRoles(StatusId $statusId): void
    {
        $this->entityManager
            ->createQueryBuilder()
            ->delete(StatusRoleRecord::class, 'sr')
            ->where('sr.statusId = :statusId')
            ->setParameter('statusId', $statusId->value())
            ->getQuery()
            ->execute();
    }
}
