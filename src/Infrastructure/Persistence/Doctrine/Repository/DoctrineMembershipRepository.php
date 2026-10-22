<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Contract\Tenancy\MembershipRepository;
use App\Domain\Tenancy\Membership;
use App\Domain\Tenancy\MembershipRole;
use App\Domain\Tenancy\UserId;
use App\Domain\Tenancy\WorkspaceId;
use App\Infrastructure\Persistence\Doctrine\Entity\MembershipRecord;
use App\Infrastructure\Persistence\Doctrine\Mapper\MembershipRecordMapper;
use Doctrine\ORM\EntityManagerInterface;
use Override;

final readonly class DoctrineMembershipRepository implements MembershipRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MembershipRecordMapper $mapper,
    ) {
    }

    #[Override]
    public function add(Membership $membership): void
    {
        $this->entityManager->persist($this->mapper->newRecord($membership));
        $this->entityManager->flush();
    }

    #[Override]
    public function update(Membership $membership): void
    {
        $record = $this->entityManager->find(MembershipRecord::class, [
            'workspaceId' => $membership->workspaceId()->value(),
            'userId' => $membership->userId()->value(),
        ]);
        if ($record === null) {
            return;
        }

        $this->mapper->sync($record, $membership);
        $this->entityManager->flush();
    }

    #[Override]
    public function remove(WorkspaceId $workspaceId, UserId $userId): void
    {
        $record = $this->entityManager->find(MembershipRecord::class, [
            'workspaceId' => $workspaceId->value(),
            'userId' => $userId->value(),
        ]);
        if ($record === null) {
            return;
        }

        $this->entityManager->remove($record);
        $this->entityManager->flush();
    }

    #[Override]
    public function find(WorkspaceId $workspaceId, UserId $userId): ?Membership
    {
        $record = $this->entityManager->find(MembershipRecord::class, [
            'workspaceId' => $workspaceId->value(),
            'userId' => $userId->value(),
        ]);

        return $record === null ? null : $this->mapper->toDomain($record);
    }

    #[Override]
    public function forWorkspace(WorkspaceId $workspaceId): array
    {
        /** @var list<MembershipRecord> $records */
        $records = $this->entityManager
            ->createQueryBuilder()
            ->select('m')
            ->from(MembershipRecord::class, 'm')
            ->where('m.workspaceId = :workspaceId')
            ->setParameter('workspaceId', $workspaceId->value())
            ->orderBy('m.createdAt', 'ASC')
            ->getQuery()
            ->getResult();

        return array_map(
            fn(MembershipRecord $record): Membership => $this->mapper->toDomain($record),
            $records,
        );
    }

    #[Override]
    public function forUser(UserId $userId): array
    {
        /** @var list<MembershipRecord> $records */
        $records = $this->entityManager
            ->createQueryBuilder()
            ->select('m')
            ->from(MembershipRecord::class, 'm')
            ->where('m.userId = :userId')
            ->setParameter('userId', $userId->value())
            ->orderBy('m.createdAt', 'ASC')
            ->getQuery()
            ->getResult();

        return array_map(
            fn(MembershipRecord $record): Membership => $this->mapper->toDomain($record),
            $records,
        );
    }

    #[Override]
    public function countByRole(WorkspaceId $workspaceId, MembershipRole $role): int
    {
        $count = $this->entityManager
            ->createQueryBuilder()
            ->select('COUNT(m.workspaceId)')
            ->from(MembershipRecord::class, 'm')
            ->where('m.workspaceId = :workspaceId')
            ->andWhere('m.role = :role')
            ->setParameter('workspaceId', $workspaceId->value())
            ->setParameter('role', $role->value)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count;
    }
}
