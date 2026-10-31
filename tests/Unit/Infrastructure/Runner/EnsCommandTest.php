<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Runner;

use PHPUnit\Framework\TestCase;

final class EnsCommandTest extends TestCase
{
    private string $outcomePath;

    protected function setUp(): void
    {
        $this->outcomePath = sys_get_temp_dir() . '/ensemble-ens-test-' . bin2hex(random_bytes(6)) . '.json';
    }

    protected function tearDown(): void
    {
        @unlink($this->outcomePath);
    }

    public function testWritesAnAllowedTestResult(): void
    {
        [$code, $output] = $this->runCommand('test pass', 'test_passed,test_failed,blocked');
        self::assertSame(0, $code, $output);

        $data = json_decode((string) file_get_contents($this->outcomePath), true);
        self::assertSame('test_passed', $data['outcome']);
    }

    public function testRequiresAReasonForAFailure(): void
    {
        [$code] = $this->runCommand('test fail', 'test_passed,test_failed,blocked');

        self::assertNotSame(0, $code);
        self::assertFileDoesNotExist($this->outcomePath);
    }

    public function testRejectsAnOutcomeOutsideTheStage(): void
    {
        [$code] = $this->runCommand('review approve', 'test_passed,test_failed,blocked');

        self::assertNotSame(0, $code);
        self::assertFileDoesNotExist($this->outcomePath);
    }

    /** @return array{0: int, 1: string} */
    private function runCommand(string $command, string $allowed): array
    {
        $bin = dirname(__DIR__, 4) . '/bin/ens';
        $line = sprintf(
            'ENS_OUTCOME_FILE=%s ENS_ALLOWED_OUTCOMES=%s %s %s %s',
            escapeshellarg($this->outcomePath),
            escapeshellarg($allowed),
            escapeshellarg(PHP_BINARY),
            escapeshellarg($bin),
            $command,
        );
        exec($line . ' 2>&1', $output, $code);

        return [$code, implode("\n", $output)];
    }
}
