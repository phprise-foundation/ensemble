<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use RuntimeException;

final class JsonFile
{
    public function __construct(private string $path)
    {
    }

    /** @return array<array-key, mixed> */
    public function read(): array
    {
        if (!is_file($this->path)) {
            return [];
        }
        $handle = $this->open('r');
        flock($handle, LOCK_SH);
        $data = $this->decode((string) stream_get_contents($handle));
        flock($handle, LOCK_UN);
        fclose($handle);

        return $data;
    }

    public function update(callable $fn): void
    {
        $this->ensureDirectory();
        $handle = $this->open('c+');
        flock($handle, LOCK_EX);
        $data = $this->decode((string) stream_get_contents($handle));
        $fn($data);
        $this->write($handle, $data);
        flock($handle, LOCK_UN);
        fclose($handle);
    }

    /** @return resource */
    private function open(string $mode)
    {
        $handle = fopen($this->path, $mode);
        if ($handle === false) {
            throw new RuntimeException('Unable to open board storage.');
        }

        return $handle;
    }

    private function ensureDirectory(): void
    {
        $directory = dirname($this->path);
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
    }

    /** @return array<array-key, mixed> */
    private function decode(string $raw): array
    {
        /** @var mixed $data */
        $data = json_decode($raw, true);

        return is_array($data) ? $data : [];
    }

    /** @param resource $handle */
    private function write($handle, array $data): void
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, (string) $json);
        fflush($handle);
    }
}
