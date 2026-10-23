<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Contract\Team\TeamRepository;
use App\Domain\Team\Team;
use App\Domain\Team\TeamId;
use App\Domain\Tenancy\WorkspaceId;
use App\Infrastructure\Persistence\Doctrine\Entity\TeamRecord;
use App\Infrastructure\Persistence\Doctrine\Mapper\TeamRecordMapper;
use Doctrine\ORM\EntityManagerInterface;
use Override;

final readonly class DoctrineTeamRepository implements TeamRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TeamRecordMapper $mapper,
    ) {
    }

    #[Override]
    public function add(Team $team): void
    {
        $this->entityManager->persist($this->mapper->newRecord($team));
        $this->entityManager->flush();
    }

    #[Override]
    public function update(Team $team): void
    {
        $record = $this->entityManager->find(TeamRecord::class, $team->id()->value());
        if ($record === null) {
            return;
        }

        $this->mapper->sync($record, $team);
        $this->entityManager->flush();
    }

    #[Override]
    public function delete(Team $team): void
    {
        $record = $this->entityManager->find(TeamRecord::class, $team->id()->value());
        if ($record === null) {
            return;
        }

        $this->entityManager->remove($record);
        $this->entityManager->flush();
    }

    #[Override]
    public function find(WorkspaceId $workspaceId, TeamId $id): ?Team
    {
        /** @var TeamRecord|null $record */
        $record = $this->entityManager
            ->createQueryBuilder()
            ->select('t')
            ->from(TeamRecord::class, 't')
            ->where('t.id = :id')
            ->andWhere('t.workspaceId = :workspaceId')
            ->setParameter('id', $id->value())
            ->setParameter('workspaceId', $workspaceId->value())
            ->getQuery()
            ->getOneOrNullResult();

        return $record === null ? null : $this->mapper->toDomain($record);
    }

    #[Override]
    public function all(WorkspaceId $workspaceId): array
    {
        /** @var list<TeamRecord> $records */
        $records = $this->entityManager
            ->createQueryBuilder()
            ->select('t')
            ->from(TeamRecord::class, 't')
            ->where('t.workspaceId = :workspaceId')
            ->setParameter('workspaceId', $workspaceId->value())
            ->orderBy('t.name', 'ASC')
            ->getQuery()
            ->getResult();

        return array_map(fn(TeamRecord $record): Team => $this->mapper->toDomain($record), $records);
    }
}
