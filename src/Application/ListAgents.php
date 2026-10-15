<?php

declare(strict_types=1);

namespace App\Application;

use App\Domain\Agent\Agent;
use App\Domain\Contract\AgentRegistry;

final readonly class ListAgents
{
    public function __construct(private AgentRegistry $agents)
    {
    }

    /** @return Agent[] */
    public function execute(): array
    {
        return $this->agents->all();
    }
}
