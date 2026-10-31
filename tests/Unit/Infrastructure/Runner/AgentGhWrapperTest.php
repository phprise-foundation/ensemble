<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Runner;

use PHPUnit\Framework\TestCase;

final class AgentGhWrapperTest extends TestCase
{
    public function testDeniesMergeAndRawApiAccess(): void
    {
        $gh = dirname(__DIR__, 4) . '/bin/agent/gh';

        exec(escapeshellarg($gh) . ' pr merge 1 2>&1', $mergeOutput, $mergeCode);
        exec(escapeshellarg($gh) . ' api repos/acme/app 2>&1', $apiOutput, $apiCode);

        self::assertNotSame(0, $mergeCode);
        self::assertStringContainsString('not permitted', implode("\n", $mergeOutput));
        self::assertNotSame(0, $apiCode);
        self::assertStringContainsString('not permitted', implode("\n", $apiOutput));
    }
}
