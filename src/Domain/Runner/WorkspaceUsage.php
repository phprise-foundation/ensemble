<?php

declare(strict_types=1);

namespace App\Domain\Runner;

final readonly class WorkspaceUsage
{
    public function __construct(
        private int $chargedMicros,
        private int $actualCostMicros,
        private int $requests,
        private int $inputTokens,
        private int $outputTokens,
        private int $reasoningTokens,
        private int $cacheReadTokens,
        private int $cacheWriteTokens,
    ) {
    }

    public function chargedMicros(): int
    {
        return $this->chargedMicros;
    }

    public function actualCostMicros(): int
    {
        return $this->actualCostMicros;
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
}
