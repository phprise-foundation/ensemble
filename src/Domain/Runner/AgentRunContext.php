<?php

declare(strict_types=1);

namespace App\Domain\Runner;

final readonly class AgentRunContext
{
    public function __construct(
        private string $outcomePath,
        private string $stage,
        private string $role,
        /** @var list<string> */
        private array $allowedOutcomes,
        private string $commentPath,
    ) {
    }

    public function outcomePath(): string
    {
        return $this->outcomePath;
    }

    public function stage(): string
    {
        return $this->stage;
    }

    public function role(): string
    {
        return $this->role;
    }

    /** @return list<string> */
    public function allowedOutcomes(): array
    {
        return $this->allowedOutcomes;
    }

    public function commentPath(): string
    {
        return $this->commentPath;
    }
}
