<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Contract\Runner\RunUsageRepository;
use App\Domain\Runner\AgentUsage;
use App\Domain\Runner\RunUsage;
use App\Domain\Runner\WorkspaceUsage;
use App\Domain\Tenancy\WorkspaceId;
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
    public function chargedForWorkspace(WorkspaceId $workspaceId): int
    {
        return $this->summaryForWorkspace($workspaceId)->chargedMicros();
    }

    #[Override]
    public function summaryForWorkspace(WorkspaceId $workspaceId, ?DateTimeImmutable $since = null): WorkspaceUsage
    {
        $parameters = ['workspace' => $workspaceId->value()];
        $filter = '';
        if ($since !== null) {
            $parameters['since'] = $since->format('Y-m-d H:i:s');
            $filter = ' AND created_at >= :since';
        }
        $row = $this->entityManager->getConnection()->fetchAssociative(
            'SELECT COALESCE(SUM(charged_cost_micros), 0) AS charged,
                    COALESCE(SUM(actual_cost_micros), 0) AS actual,
                    COALESCE(SUM(requests), 0) AS requests,
                    COALESCE(SUM(input_tokens), 0) AS input_tokens,
                    COALESCE(SUM(output_tokens), 0) AS output_tokens,
                    COALESCE(SUM(reasoning_tokens), 0) AS reasoning_tokens,
                    COALESCE(SUM(cache_read_tokens), 0) AS cache_read_tokens,
                    COALESCE(SUM(cache_write_tokens), 0) AS cache_write_tokens
             FROM run_usage
             WHERE workspace_id = :workspace' . $filter,
            $parameters,
        );
        if ($row === false) {
            return new WorkspaceUsage(0, 0, 0, 0, 0, 0, 0, 0);
        }

        return new WorkspaceUsage(
            (int) $row['charged'],
            (int) $row['actual'],
            (int) $row['requests'],
            (int) $row['input_tokens'],
            (int) $row['output_tokens'],
            (int) $row['reasoning_tokens'],
            (int) $row['cache_read_tokens'],
            (int) $row['cache_write_tokens'],
        );
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
