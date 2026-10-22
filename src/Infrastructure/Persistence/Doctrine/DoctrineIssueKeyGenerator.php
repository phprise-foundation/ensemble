<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine;

use App\Domain\Contract\Kanban\IssueKeyGenerator;
use App\Domain\Kanban\IssueKey;
use App\Domain\Tenancy\WorkspaceId;
use App\Domain\Tenancy\WorkspacePrefix;
use App\Infrastructure\Persistence\Doctrine\Entity\IssueSequenceRecord;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Override;

final readonly class DoctrineIssueKeyGenerator implements IssueKeyGenerator
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    #[Override]
    public function next(WorkspaceId $workspaceId, WorkspacePrefix $prefix): IssueKey
    {
        $key = $this->entityManager->wrapInTransaction(function () use ($workspaceId, $prefix): IssueKey {
            $sequence = $this->entityManager->find(
                IssueSequenceRecord::class,
                $workspaceId->value(),
                LockMode::PESSIMISTIC_WRITE,
            );
            if ($sequence === null) {
                $this->entityManager->persist(new IssueSequenceRecord($workspaceId->value(), 2));
                $this->entityManager->flush();

                return IssueKey::from($prefix, 1);
            }

            $number = $sequence->nextNumber();
            $sequence->advance();
            $this->entityManager->flush();

            return IssueKey::from($prefix, $number);
        });

        return $key;
    }
}
