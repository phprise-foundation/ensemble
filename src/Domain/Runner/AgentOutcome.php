<?php

declare(strict_types=1);

namespace App\Domain\Runner;

final readonly class AgentOutcome
{
    private function __construct(
        private AgentOutcomeType $type,
        private string $reason,
    ) {
    }

    public static function none(): self
    {
        return new self(AgentOutcomeType::None, '');
    }

    public static function invalid(string $reason): self
    {
        return new self(AgentOutcomeType::Invalid, $reason);
    }

    public static function of(AgentOutcomeType $type, string $reason): self
    {
        return new self($type, $reason);
    }

    public function type(): AgentOutcomeType
    {
        return $this->type;
    }

    public function reason(): string
    {
        return $this->reason;
    }

    public function isFailure(): bool
    {
        return in_array($this->type, [
            AgentOutcomeType::TestFailed,
            AgentOutcomeType::ChangesRequested,
            AgentOutcomeType::Blocked,
            AgentOutcomeType::Invalid,
        ], true);
    }
}
