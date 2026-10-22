<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Contract\Kanban\FieldDefinitionRepository;
use App\Domain\Kanban\FieldDefinition;
use App\Domain\Kanban\FieldDefinitionId;
use App\Domain\Kanban\IssueTypeId;
use App\Domain\Kanban\Slug;
use App\Domain\Tenancy\WorkspaceId;
use App\Infrastructure\Persistence\Doctrine\Entity\FieldDefinitionRecord;
use App\Infrastructure\Persistence\Doctrine\Mapper\FieldDefinitionRecordMapper;
use Doctrine\ORM\EntityManagerInterface;
use Override;

final readonly class DoctrineFieldDefinitionRepository implements FieldDefinitionRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private FieldDefinitionRecordMapper $mapper,
    ) {
    }

    #[Override]
    public function add(FieldDefinition $field): void
    {
        $this->entityManager->persist($this->mapper->newRecord($field));
        $this->entityManager->flush();
    }

    #[Override]
    public function update(FieldDefinition $field): void
    {
        $record = $this->entityManager->find(FieldDefinitionRecord::class, $field->id()->value());
        if ($record === null) {
            return;
        }

        $this->mapper->sync($record, $field);
        $this->entityManager->flush();
    }

    #[Override]
    public function delete(FieldDefinition $field): void
    {
        $record = $this->entityManager->find(FieldDefinitionRecord::class, $field->id()->value());
        if ($record === null) {
            return;
        }

        $this->entityManager->remove($record);
        $this->entityManager->flush();
    }

    #[Override]
    public function find(WorkspaceId $workspaceId, FieldDefinitionId $id): ?FieldDefinition
    {
        /** @var FieldDefinitionRecord|null $record */
        $record = $this->entityManager
            ->createQueryBuilder()
            ->select('f')
            ->from(FieldDefinitionRecord::class, 'f')
            ->where('f.id = :id')
            ->andWhere('f.workspaceId = :workspaceId')
            ->setParameter('id', $id->value())
            ->setParameter('workspaceId', $workspaceId->value())
            ->getQuery()
            ->getOneOrNullResult();

        return $record instanceof FieldDefinitionRecord ? $this->mapper->toDomain($record) : null;
    }

    #[Override]
    public function findByKey(IssueTypeId $issueTypeId, Slug $key): ?FieldDefinition
    {
        /** @var FieldDefinitionRecord|null $record */
        $record = $this->entityManager
            ->createQueryBuilder()
            ->select('f')
            ->from(FieldDefinitionRecord::class, 'f')
            ->where('f.issueTypeId = :issueTypeId')
            ->andWhere('f.key = :key')
            ->setParameter('issueTypeId', $issueTypeId->value())
            ->setParameter('key', $key->value())
            ->getQuery()
            ->getOneOrNullResult();

        return $record instanceof FieldDefinitionRecord ? $this->mapper->toDomain($record) : null;
    }

    #[Override]
    public function forIssueType(WorkspaceId $workspaceId, IssueTypeId $issueTypeId): array
    {
        /** @var list<FieldDefinitionRecord> $records */
        $records = $this->entityManager
            ->createQueryBuilder()
            ->select('f')
            ->from(FieldDefinitionRecord::class, 'f')
            ->where('f.issueTypeId = :issueTypeId')
            ->andWhere('f.workspaceId = :workspaceId')
            ->setParameter('issueTypeId', $issueTypeId->value())
            ->setParameter('workspaceId', $workspaceId->value())
            ->orderBy('f.position', 'ASC')
            ->addOrderBy('f.label', 'ASC')
            ->getQuery()
            ->getResult();

        return array_map(fn(FieldDefinitionRecord $record): FieldDefinition => $this->mapper->toDomain($record), $records);
    }

    #[Override]
    public function countForIssueType(IssueTypeId $issueTypeId): int
    {
        $count = $this->entityManager
            ->createQueryBuilder()
            ->select('COUNT(f.id)')
            ->from(FieldDefinitionRecord::class, 'f')
            ->where('f.issueTypeId = :issueTypeId')
            ->setParameter('issueTypeId', $issueTypeId->value())
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count;
    }
}
