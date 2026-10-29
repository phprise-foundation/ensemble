<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Runner;

use App\Infrastructure\Runner\HeartbeatFile;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class HeartbeatFileTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir() . '/ensemble-heartbeat-' . bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        @unlink($this->path);
    }

    public function testMissingFileIsOffline(): void
    {
        self::assertSame(
            ['online' => false, 'lastSeenAt' => null],
            (new HeartbeatFile($this->path))->read(),
        );
    }

    public function testFreshHeartbeatIsOnline(): void
    {
        $heartbeat = new HeartbeatFile($this->path);
        $heartbeat->write(new DateTimeImmutable());

        $status = $heartbeat->read();
        self::assertTrue($status['online']);
        self::assertNotNull($status['lastSeenAt']);
    }

    public function testOldHeartbeatIsOffline(): void
    {
        $heartbeat = new HeartbeatFile($this->path, 30);
        $heartbeat->write(new DateTimeImmutable('-2 minutes'));

        self::assertFalse($heartbeat->read()['online']);
    }
}
