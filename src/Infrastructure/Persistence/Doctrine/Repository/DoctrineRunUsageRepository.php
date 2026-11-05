<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Contract\Runner\RunUsageRepository;
use App\Domain\Runner\RunUsage;
use App\Infrastructure\Persistence\Doctrine\Mapper\RunUsageRecordMapper;
use Doctrine\ORM\EntityManagerInterface;
use Override;

final readonly class DoctrineRunUsageRepository implements RunUsageRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private RunUsageRecordMapper $mapper,
    ) {
    }

    #[Override]
    public function record(RunUsage $usage): void
    {
        $this->entityManager->persist($this->mapper->newRecord($usage));
        $this->entityManager->flush();
    }
}
