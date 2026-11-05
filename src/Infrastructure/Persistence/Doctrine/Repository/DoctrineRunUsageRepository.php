<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Contract\Runner\RunUsageRepository;
use App\Domain\Runner\AgentUsage;
use App\Domain\Runner\RunUsage;
use App\Domain\Team\AgentId;
use App\Infrastructure\Persistence\Doctrine\Mapper\RunUsageRecordMapper;
use DateTimeImmutable;
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

    #[Override]
    public function chargedSince(AgentId $agentId, DateTimeImmutable $since): int
    {
        return (int) $this->entityManager->getConnection()->fetchOne(
            'SELECT COALESCE(SUM(charged_cost_micros), 0)
             FROM run_usage
             WHERE agent_id = :agent AND created_at >= :since',
            ['agent' => $agentId->value(), 'since' => $since->format('Y-m-d H:i:s')],
        );
    }

    #[Override]
    public function usageSince(AgentId $agentId, DateTimeImmutable $since): AgentUsage
    {
        $row = $this->entityManager->getConnection()->fetchAssociative(
            'SELECT COALESCE(SUM(requests), 0) AS requests,
                    COALESCE(SUM(input_tokens + output_tokens + reasoning_tokens + cache_read_tokens + cache_write_tokens), 0) AS tokens
             FROM run_usage
             WHERE agent_id = :agent AND created_at >= :since',
            ['agent' => $agentId->value(), 'since' => $since->format('Y-m-d H:i:s')],
        );
        if ($row === false) {
            return new AgentUsage(0, 0);
        }

        return new AgentUsage((int) $row['requests'], (int) $row['tokens']);
    }
}
