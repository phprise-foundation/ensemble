<?php

declare(strict_types=1);

namespace App\Domain\Contract;

use App\Domain\Agent\Agent;
use App\Domain\Agent\AgentId;

interface AgentRegistry
{
    /** @return Agent[] */
    public function all(): array;

    public function find(AgentId $id): ?Agent;

    public function save(Agent $agent): void;

    public function delete(AgentId $id): void;
}
