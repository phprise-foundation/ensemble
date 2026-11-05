<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Mapper;

use App\Domain\Runner\RunUsage;
use App\Infrastructure\Persistence\Doctrine\Entity\RunUsageRecord;

final class RunUsageRecordMapper
{
    public function newRecord(RunUsage $usage): RunUsageRecord
    {
        $model = $usage->usage();

        return new RunUsageRecord(
            $usage->id()->value(),
            $usage->runId()->value(),
            $usage->workspaceId()->value(),
            $usage->agentId()->value(),
            $usage->issueId()->value(),
            $usage->modelId()->value(),
            $model->plan()->value,
            $model->providerModel(),
            $model->requests(),
            $model->inputTokens(),
            $model->outputTokens(),
            $model->reasoningTokens(),
            $model->cacheReadTokens(),
            $model->cacheWriteTokens(),
            $model->actualCostMicros(),
            $usage->chargedCostMicros(),
            $usage->createdAt(),
        );
    }
}
