<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Contract\Team\AgentRepository;
use App\Domain\Team\Agent;
use App\Domain\Team\AgentId;
use App\Domain\Team\RoleIds;
use App\Domain\Team\RoleId;
use App\Domain\Team\TeamId;
use App\Domain\Tenancy\WorkspaceId;
use App\Infrastructure\Persistence\Doctrine\Entity\AgentRecord;
use App\Infrastructure\Persistence\Doctrine\Entity\AgentRoleRecord;
use App\Infrastructure\Persistence\Doctrine\Mapper\AgentRecordMapper;
use Doctrine\ORM\EntityManagerInterface;
use Override;

final readonly class DoctrineAgentRepository implements AgentRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AgentRecordMapper $mapper,
    ) {
    }

    #[Override]
    public function add(Agent $agent): void
    {
        $this->entityManager->persist($this->mapper->newRecord($agent));
        $this->syncRoles($agent);
        $this->entityManager->flush();
    }

    #[Override]
    public function update(Agent $agent): void
    {
        $record = $this->entityManager->find(AgentRecord::class, $agent->id()->value());
        if ($record === null) {
            return;
        }

        $this->mapper->sync($record, $agent);
        $this->syncRoles($agent);
        $this->entityManager->flush();
    }

    #[Override]
    public function delete(Agent $agent): void
    {
        $record = $this->entityManager->find(AgentRecord::class, $agent->id()->value());
        if ($record === null) {
            return;
        }

        $this->entityManager
            ->createQueryBuilder()
            ->delete(AgentRoleRecord::class, 'ar')
            ->where('ar.agentId = :agentId')
            ->setParameter('agentId', $agent->id()->value())
            ->getQuery()
            ->execute();
        $this->entityManager->remove($record);
        $this->entityManager->flush();
    }

    #[Override]
    public function find(WorkspaceId $workspaceId, AgentId $id): ?Agent
    {
        /** @var AgentRecord|null $record */
        $record = $this->entityManager
            ->createQueryBuilder()
            ->select('a')
            ->from(AgentRecord::class, 'a')
            ->where('a.id = :id')
            ->andWhere('a.workspaceId = :workspaceId')
            ->setParameter('id', $id->value())
            ->setParameter('workspaceId', $workspaceId->value())
            ->getQuery()
            ->getOneOrNullResult();

        return $record === null ? null : $this->mapper->toDomain($record, $this->rolesFor($record->id()));
    }

    #[Override]
    public function forTeam(WorkspaceId $workspaceId, TeamId $teamId): array
    {
        /** @var list<AgentRecord> $records */
        $records = $this->entityManager
            ->createQueryBuilder()
            ->select('a')
            ->from(AgentRecord::class, 'a')
            ->where('a.teamId = :teamId')
            ->andWhere('a.workspaceId = :workspaceId')
            ->setParameter('teamId', $teamId->value())
            ->setParameter('workspaceId', $workspaceId->value())
            ->orderBy('a.name', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->domains($records);
    }

    #[Override]
    public function forWorkspace(WorkspaceId $workspaceId): array
    {
        /** @var list<AgentRecord> $records */
        $records = $this->entityManager
            ->createQueryBuilder()
            ->select('a')
            ->from(AgentRecord::class, 'a')
            ->where('a.workspaceId = :workspaceId')
            ->setParameter('workspaceId', $workspaceId->value())
            ->orderBy('a.name', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->domains($records);
    }

    /**
     * @param list<AgentRecord> $records
     * @return list<Agent>
     */
    private function domains(array $records): array
    {
        return array_map(
            fn(AgentRecord $record): Agent => $this->mapper->toDomain($record, $this->rolesFor($record->id())),
            $records,
        );
    }

    private function rolesFor(string $agentId): RoleIds
    {
        /** @var list<AgentRoleRecord> $records */
        $records = $this->entityManager
            ->createQueryBuilder()
            ->select('ar')
            ->from(AgentRoleRecord::class, 'ar')
            ->where('ar.agentId = :agentId')
            ->setParameter('agentId', $agentId)
            ->getQuery()
            ->getResult();

        return new RoleIds(array_map(
            fn(AgentRoleRecord $record): RoleId => new RoleId($record->roleId()),
            $records,
        ));
    }

    private function syncRoles(Agent $agent): void
    {
        /** @var list<AgentRoleRecord> $existing */
        $existing = $this->entityManager
            ->createQueryBuilder()
            ->select('ar')
            ->from(AgentRoleRecord::class, 'ar')
            ->where('ar.agentId = :agentId')
            ->setParameter('agentId', $agent->id()->value())
            ->getQuery()
            ->getResult();

        $desired = [];
        foreach ($agent->roleIds() as $roleId) {
            $desired[$roleId->value()] = $roleId->value();
        }

        foreach ($existing as $record) {
            if (isset($desired[$record->roleId()])) {
                unset($desired[$record->roleId()]);
                continue;
            }

            $this->entityManager->remove($record);
        }

        foreach (array_values($desired) as $roleId) {
            $this->entityManager->persist(new AgentRoleRecord($agent->id()->value(), $roleId));
        }
    }
}
