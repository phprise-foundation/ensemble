<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Contract\Vcs\GitRepositories;
use App\Domain\Security\WorkspaceSecretId;
use App\Domain\Tenancy\WorkspaceId;
use App\Domain\Vcs\GitRepository;
use App\Domain\Vcs\GitRepositoryId;
use App\Infrastructure\Persistence\Doctrine\Entity\GitRepositoryRecord;
use App\Infrastructure\Persistence\Doctrine\Mapper\GitRepositoryRecordMapper;
use Doctrine\ORM\EntityManagerInterface;
use Override;

final readonly class DoctrineGitRepositories implements GitRepositories
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private GitRepositoryRecordMapper $mapper,
    ) {
    }

    #[Override]
    public function add(GitRepository $repository): void
    {
        $this->entityManager->persist($this->mapper->newRecord($repository));
        $this->entityManager->flush();
    }

    #[Override]
    public function update(GitRepository $repository): void
    {
        $record = $this->entityManager->find(GitRepositoryRecord::class, $repository->id()->value());
        if ($record === null) {
            return;
        }

        $this->mapper->sync($record, $repository);
        $this->entityManager->flush();
    }

    #[Override]
    public function delete(GitRepository $repository): void
    {
        $record = $this->entityManager->find(GitRepositoryRecord::class, $repository->id()->value());
        if ($record === null) {
            return;
        }

        $this->entityManager->remove($record);
        $this->entityManager->flush();
    }

    #[Override]
    public function find(WorkspaceId $workspaceId, GitRepositoryId $id): ?GitRepository
    {
        /** @var GitRepositoryRecord|null $record */
        $record = $this->entityManager
            ->createQueryBuilder()
            ->select('r')
            ->from(GitRepositoryRecord::class, 'r')
            ->where('r.id = :id')
            ->andWhere('r.workspaceId = :workspaceId')
            ->setParameter('id', $id->value())
            ->setParameter('workspaceId', $workspaceId->value())
            ->getQuery()
            ->getOneOrNullResult();

        return $record === null ? null : $this->mapper->toDomain($record);
    }

    #[Override]
    public function findByName(WorkspaceId $workspaceId, string $name): ?GitRepository
    {
        /** @var GitRepositoryRecord|null $record */
        $record = $this->entityManager
            ->createQueryBuilder()
            ->select('r')
            ->from(GitRepositoryRecord::class, 'r')
            ->where('r.name = :name')
            ->andWhere('r.workspaceId = :workspaceId')
            ->setParameter('name', $name)
            ->setParameter('workspaceId', $workspaceId->value())
            ->getQuery()
            ->getOneOrNullResult();

        return $record === null ? null : $this->mapper->toDomain($record);
    }

    #[Override]
    public function all(WorkspaceId $workspaceId): array
    {
        /** @var list<GitRepositoryRecord> $records */
        $records = $this->entityManager
            ->createQueryBuilder()
            ->select('r')
            ->from(GitRepositoryRecord::class, 'r')
            ->where('r.workspaceId = :workspaceId')
            ->setParameter('workspaceId', $workspaceId->value())
            ->orderBy('r.name', 'ASC')
            ->getQuery()
            ->getResult();

        return array_map(fn(GitRepositoryRecord $record): GitRepository => $this->mapper->toDomain($record), $records);
    }

    #[Override]
    public function referenced(WorkspaceSecretId $secretId): bool
    {
        $count = $this->entityManager
            ->createQueryBuilder()
            ->select('COUNT(r.id)')
            ->from(GitRepositoryRecord::class, 'r')
            ->where('r.secretId = :secretId')
            ->setParameter('secretId', $secretId->value())
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count > 0;
    }
}
