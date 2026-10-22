<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Contract\Tenancy\SessionRepository;
use App\Domain\Security\Session;
use App\Domain\Security\SessionTokenHash;
use App\Infrastructure\Persistence\Doctrine\Entity\SessionRecord;
use App\Infrastructure\Persistence\Doctrine\Mapper\SessionRecordMapper;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Override;

final readonly class DoctrineSessionRepository implements SessionRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SessionRecordMapper $mapper,
    ) {
    }

    #[Override]
    public function add(Session $session): void
    {
        $this->entityManager->persist($this->mapper->newRecord($session));
        $this->entityManager->flush();
    }

    #[Override]
    public function find(SessionTokenHash $tokenHash): ?Session
    {
        $record = $this->entityManager->find(SessionRecord::class, $tokenHash->value());

        return $record === null ? null : $this->mapper->toDomain($record);
    }

    #[Override]
    public function delete(SessionTokenHash $tokenHash): void
    {
        $record = $this->entityManager->find(SessionRecord::class, $tokenHash->value());
        if ($record === null) {
            return;
        }

        $this->entityManager->remove($record);
        $this->entityManager->flush();
    }

    #[Override]
    public function deleteExpired(DateTimeImmutable $now): void
    {
        $this->entityManager
            ->createQueryBuilder()
            ->delete(SessionRecord::class, 's')
            ->where('s.expiresAt <= :now')
            ->setParameter('now', $now)
            ->getQuery()
            ->execute();
    }
}
