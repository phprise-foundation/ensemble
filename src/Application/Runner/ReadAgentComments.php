<?php

declare(strict_types=1);

namespace App\Application\Runner;

final readonly class ReadAgentComments
{
    private const MAX_COMMENTS = 10;

    /** @return list<string> */
    public function execute(string $path): array
    {
        if (! is_file($path)) {
            return [];
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return [];
        }

        $comments = [];
        foreach ($lines as $line) {
            /** @var mixed $data */
            $data = json_decode($line, true);
            if (! is_array($data) || ! isset($data['body']) || ! is_string($data['body'])) {
                continue;
            }
            $body = trim($data['body']);
            if ($body === '' || mb_strlen($body) > 1023) {
                continue;
            }
            $comments[] = $body;
            if (count($comments) >= self::MAX_COMMENTS) {
                break;
            }
        }

        return $comments;
    }
}
