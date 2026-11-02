<?php

declare(strict_types=1);

namespace App\Application\Runner;

final readonly class ReadAgentDescription
{
    public function execute(string $path): ?string
    {
        if (! is_file($path)) {
            return null;
        }

        $raw = file_get_contents($path);
        if ($raw === false || trim($raw) === '') {
            return null;
        }

        /** @var mixed $data */
        $data = json_decode($raw, true);
        if (! is_array($data) || ! isset($data['body']) || ! is_string($data['body'])) {
            return null;
        }
        $body = trim($data['body']);

        return $body === '' || mb_strlen($body) > 4095 ? null : $body;
    }
}
