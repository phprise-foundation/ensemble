<?php

declare(strict_types=1);

namespace App\Application;

use App\Domain\Agent\AgentId;
use App\Domain\Contract\AgentRegistry;

final readonly class DeleteAgent
{
    public function __construct(private AgentRegistry $agents)
    {
    }

    public function execute(AgentId $id): void
    {
        $this->agents->delete($id);
    }
}
