<?php

declare(strict_types=1);

namespace App\Infrastructure\Runner;

use DateTimeImmutable;

final readonly class HeartbeatFile
{
    public function __construct(
        private string $path,
        private int $staleAfterSeconds = 30,
    ) {
    }

    public function write(DateTimeImmutable $at): void
    {
        $directory = dirname($this->path);
        if (! is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }

        file_put_contents($this->path, (string) $at->getTimestamp());
    }

    /** @return array{online: bool, lastSeenAt: ?string} */
    public function read(): array
    {
        if (! is_file($this->path) || ! is_readable($this->path)) {
            return ['online' => false, 'lastSeenAt' => null];
        }

        $seen = (int) trim((string) file_get_contents($this->path));
        if ($seen <= 0) {
            return ['online' => false, 'lastSeenAt' => null];
        }

        return [
            'online' => time() - $seen <= $this->staleAfterSeconds,
            'lastSeenAt' => gmdate(DATE_ATOM, $seen),
        ];
    }
}
