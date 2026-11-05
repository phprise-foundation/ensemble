<?php

declare(strict_types=1);

namespace App\Domain\Runner;

final readonly class UsageReport
{
    /** @param list<ModelUsage> $entries */
    public function __construct(private array $entries)
    {
    }

    /** @return list<ModelUsage> */
    public function entries(): array
    {
        return $this->entries;
    }
}
