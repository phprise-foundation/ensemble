<?php

declare(strict_types=1);

namespace App\Application\Runner;

use App\Domain\Runner\AgentOutcome;
use App\Domain\Runner\AgentOutcomeType;

final readonly class ReadAgentOutcome
{
    public function execute(string $path): AgentOutcome
    {
        if (! is_file($path)) {
            return AgentOutcome::none();
        }

        $raw = file_get_contents($path);
        if ($raw === false || trim($raw) === '') {
            return AgentOutcome::invalid('the outcome file is empty');
        }

        /** @var mixed $data */
        $data = json_decode($raw, true);
        if (! is_array($data) || ! isset($data['outcome']) || ! is_string($data['outcome'])) {
            return AgentOutcome::invalid('the outcome file has no valid outcome');
        }

        $type = AgentOutcomeType::tryFrom($data['outcome']);
        if ($type === null || $type === AgentOutcomeType::None || $type === AgentOutcomeType::Invalid) {
            return AgentOutcome::invalid('unknown outcome: ' . $data['outcome']);
        }

        $reason = isset($data['reason']) && is_string($data['reason']) ? $data['reason'] : '';

        return AgentOutcome::of($type, mb_substr($reason, 0, 500));
    }
}
