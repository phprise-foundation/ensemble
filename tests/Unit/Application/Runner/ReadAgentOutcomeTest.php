<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Runner;

use App\Application\Runner\ReadAgentOutcome;
use App\Domain\Runner\AgentOutcomeType;
use PHPUnit\Framework\TestCase;

final class ReadAgentOutcomeTest extends TestCase
{
    public function testReadsAMissingFileAsNone(): void
    {
        self::assertSame(
            AgentOutcomeType::None,
            (new ReadAgentOutcome())->execute(sys_get_temp_dir() . '/missing-outcome-' . bin2hex(random_bytes(4)))->type(),
        );
    }

    public function testReadsAValidOutcomeAndReason(): void
    {
        $path = sys_get_temp_dir() . '/ensemble-outcome-' . bin2hex(random_bytes(4)) . '.json';
        file_put_contents($path, '{"outcome":"test_failed","reason":"two tests red"}');

        $outcome = (new ReadAgentOutcome())->execute($path);
        @unlink($path);

        self::assertSame(AgentOutcomeType::TestFailed, $outcome->type());
        self::assertSame('two tests red', $outcome->reason());
    }

    public function testRejectsMalformedFiles(): void
    {
        $path = sys_get_temp_dir() . '/ensemble-outcome-' . bin2hex(random_bytes(4)) . '.json';
        file_put_contents($path, '{"outcome":"not-real"}');

        $outcome = (new ReadAgentOutcome())->execute($path);
        @unlink($path);

        self::assertSame(AgentOutcomeType::Invalid, $outcome->type());
    }
}
