<?php

declare(strict_types=1);

namespace App\Domain\Runner;

use App\Domain\Team\Agent;

/** Runs the composed prompt for an agent inside a prepared directory. */
interface AgentExecutor
{
    public function execute(Agent $agent, string $workdirPath, string $prompt, AgentRunContext $context): Outcome;
}
