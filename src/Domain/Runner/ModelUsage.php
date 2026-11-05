<?php

declare(strict_types=1);

namespace App\Domain\Runner;

use InvalidArgumentException;

final readonly class ModelUsage
{
    public function __construct(
        private string $providerModel,
        private UsagePlan $plan,
        private int $requests,
        private int $inputTokens,
        private int $outputTokens,
        private int $reasoningTokens,
        private int $cacheReadTokens,
        private int $cacheWriteTokens,
        private int $actualCostMicros,
    ) {
        if ($providerModel === '') {
            throw new InvalidArgumentException('A provider model is required.');
        }
        if ($requests < 0 || $inputTokens < 0 || $outputTokens < 0 || $reasoningTokens < 0
            || $cacheReadTokens < 0 || $cacheWriteTokens < 0 || $actualCostMicros < 0) {
            throw new InvalidArgumentException('Usage counters must not be negative.');
        }
    }

    public function providerModel(): string
    {
        return $this->providerModel;
    }

    public function plan(): UsagePlan
    {
        return $this->plan;
    }

    public function requests(): int
    {
        return $this->requests;
    }

    public function inputTokens(): int
    {
        return $this->inputTokens;
    }

    public function outputTokens(): int
    {
        return $this->outputTokens;
    }

    public function reasoningTokens(): int
    {
        return $this->reasoningTokens;
    }

    public function cacheReadTokens(): int
    {
        return $this->cacheReadTokens;
    }

    public function cacheWriteTokens(): int
    {
        return $this->cacheWriteTokens;
    }

    public function actualCostMicros(): int
    {
        return $this->actualCostMicros;
    }
}
