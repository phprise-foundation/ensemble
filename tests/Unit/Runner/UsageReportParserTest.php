<?php

declare(strict_types=1);

namespace App\Tests\Unit\Runner;

use App\Domain\Runner\UsagePlan;
use App\Infrastructure\Runner\UsageReportParser;
use PHPUnit\Framework\TestCase;

final class UsageReportParserTest extends TestCase
{
    public function testParsesByModelUsageAndPlans(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'usage');
        self::assertIsString($path);
        file_put_contents($path, json_encode([
            'model' => 'siliconflow/zai-org/GLM-5.3-Flash#low',
            'usage' => [
                'by_model' => [
                    'free-deepseek' => [
                        'requests' => 2,
                        'tokens_input' => 1_000,
                        'tokens_output' => 500,
                        'tokens_reasoning' => 100,
                        'tokens_cache_read' => 50,
                        'tokens_cache_write' => 10,
                        'cost' => 0.0003,
                    ],
                    'glm-5.3-flash' => [
                        'requests' => 1,
                        'tokens_input' => 2_000,
                        'tokens_output' => 800,
                        'tokens_reasoning' => 0,
                        'tokens_cache_read' => 0,
                        'tokens_cache_write' => 0,
                        'cost' => 0.0012,
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR));

        try {
            $report = (new UsageReportParser())->parse($path);
        } finally {
            unlink($path);
        }

        self::assertNotNull($report);
        $entries = $report->entries();
        self::assertCount(2, $entries);
        self::assertSame('free-deepseek', $entries[0]->providerModel());
        self::assertSame(UsagePlan::Free, $entries[0]->plan());
        self::assertSame(2, $entries[0]->requests());
        self::assertSame(100, $entries[0]->reasoningTokens());
        self::assertSame(300, $entries[0]->actualCostMicros());
        self::assertSame(UsagePlan::Paid, $entries[1]->plan());
        self::assertSame(1_200, $entries[1]->actualCostMicros());
    }

    public function testFallsBackToTheTotalWhenThereIsNoModelBreakdown(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'usage');
        self::assertIsString($path);
        file_put_contents($path, json_encode([
            'model' => 'free-tier/deepseek-flash',
            'usage' => [
                'total' => [
                    'requests' => 1,
                    'tokens_input' => 10,
                    'tokens_output' => 20,
                    'cost' => 0.0,
                ],
            ],
        ], JSON_THROW_ON_ERROR));

        try {
            $report = (new UsageReportParser())->parse($path);
        } finally {
            unlink($path);
        }

        self::assertNotNull($report);
        self::assertCount(1, $report->entries());
        self::assertSame(UsagePlan::Free, $report->entries()[0]->plan());
    }

    public function testReturnsNullWithoutAReport(): void
    {
        self::assertNull((new UsageReportParser())->parse('/tmp/does-not-exist-' . bin2hex(random_bytes(4))));
    }
}
