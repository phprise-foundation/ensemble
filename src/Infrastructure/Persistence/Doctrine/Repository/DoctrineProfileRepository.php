<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Contract\Team\ProfileRepository;
use App\Domain\Team\Profile;
use App\Domain\Team\ProfileId;
use App\Domain\Team\TeamId;
use App\Domain\Tenancy\WorkspaceId;
use App\Infrastructure\Persistence\Doctrine\Entity\ProfileRecord;
use App\Infrastructure\Persistence\Doctrine\Mapper\ProfileRecordMapper;
use Doctrine\ORM\EntityManagerInterface;
use Override;

final readonly class DoctrineProfileRepository implements ProfileRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ProfileRecordMapper $mapper,
    ) {
    }

    #[Override]
    public function add(Profile $profile): void
    {
        $this->entityManager->persist($this->mapper->newRecord($profile));
        $this->entityManager->flush();
    }

    #[Override]
    public function update(Profile $profile): void
    {
        $record = $this->entityManager->find(ProfileRecord::class, $profile->id()->value());
        if ($record === null) {
            return;
        }

        $this->mapper->sync($record, $profile);
        $this->entityManager->flush();
    }

    #[Override]
    public function delete(Profile $profile): void
    {
        $record = $this->entityManager->find(ProfileRecord::class, $profile->id()->value());
        if ($record === null) {
            return;
        }

        $this->entityManager->remove($record);
        $this->entityManager->flush();
    }

    #[Override]
    public function find(WorkspaceId $workspaceId, ProfileId $id): ?Profile
    {
        /** @var ProfileRecord|null $record */
        $record = $this->entityManager
            ->createQueryBuilder()
            ->select('p')
            ->from(ProfileRecord::class, 'p')
            ->where('p.id = :id')
            ->andWhere('p.workspaceId = :workspaceId')
            ->setParameter('id', $id->value())
            ->setParameter('workspaceId', $workspaceId->value())
            ->getQuery()
            ->getOneOrNullResult();

        return $record === null ? null : $this->mapper->toDomain($record);
    }

    #[Override]
    public function forTeam(WorkspaceId $workspaceId, TeamId $teamId): array
    {
        /** @var list<ProfileRecord> $records */
        $records = $this->entityManager
            ->createQueryBuilder()
            ->select('p')
            ->from(ProfileRecord::class, 'p')
            ->where('p.teamId = :teamId')
            ->andWhere('p.workspaceId = :workspaceId')
            ->setParameter('teamId', $teamId->value())
            ->setParameter('workspaceId', $workspaceId->value())
            ->orderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();

        return array_map(fn(ProfileRecord $record): Profile => $this->mapper->toDomain($record), $records);
    }

    #[Override]
    public function countForTeam(TeamId $teamId): int
    {
        $count = $this->entityManager
            ->createQueryBuilder()
            ->select('COUNT(p.id)')
            ->from(ProfileRecord::class, 'p')
            ->where('p.teamId = :teamId')
            ->setParameter('teamId', $teamId->value())
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count;
    }
}
