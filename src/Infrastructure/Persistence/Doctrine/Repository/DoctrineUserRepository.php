<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Contract\Tenancy\UserRepository;
use App\Domain\Tenancy\Login;
use App\Domain\Tenancy\User;
use App\Domain\Tenancy\UserId;
use App\Infrastructure\Persistence\Doctrine\Entity\UserRecord;
use App\Infrastructure\Persistence\Doctrine\Mapper\UserRecordMapper;
use Doctrine\ORM\EntityManagerInterface;
use Override;

final readonly class DoctrineUserRepository implements UserRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRecordMapper $mapper,
    ) {
    }

    #[Override]
    public function add(User $user): void
    {
        $this->entityManager->persist($this->mapper->newRecord($user));
        $this->entityManager->flush();
    }

    #[Override]
    public function update(User $user): void
    {
        $record = $this->entityManager->find(UserRecord::class, $user->id()->value());
        if ($record === null) {
            return;
        }

        $this->mapper->sync($record, $user);
        $this->entityManager->flush();
    }

    #[Override]
    public function find(UserId $id): ?User
    {
        $record = $this->entityManager->find(UserRecord::class, $id->value());

        return $record === null ? null : $this->mapper->toDomain($record);
    }

    #[Override]
    public function findByLogin(Login $login): ?User
    {
        $record = $this->entityManager
            ->getRepository(UserRecord::class)
            ->findOneBy(['login' => $login->value()]);

        return $record instanceof UserRecord ? $this->mapper->toDomain($record) : null;
    }

    #[Override]
    public function all(): array
    {
        /** @var list<UserRecord> $records */
        $records = $this->entityManager
            ->createQueryBuilder()
            ->select('u')
            ->from(UserRecord::class, 'u')
            ->orderBy('u.login', 'ASC')
            ->getQuery()
            ->getResult();

        return array_map(
            fn(UserRecord $record): User => $this->mapper->toDomain($record),
            $records,
        );
    }

    #[Override]
    public function existsAny(): bool
    {
        $count = $this->entityManager
            ->createQueryBuilder()
            ->select('COUNT(u.id)')
            ->from(UserRecord::class, 'u')
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count > 0;
    }
}
