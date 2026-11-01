<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Runner;

use App\Application\Runner\ReadAgentComments;
use PHPUnit\Framework\TestCase;

final class ReadAgentCommentsTest extends TestCase
{
    public function testReadsValidJsonLinesAndIgnoresNoise(): void
    {
        $path = sys_get_temp_dir() . '/ensemble-comments-' . bin2hex(random_bytes(4)) . '.jsonl';
        file_put_contents($path, '{"body":"first"}' . "\n" . 'not-json' . "\n" . '{"body":"  second  "}' . "\n");

        $comments = (new ReadAgentComments())->execute($path);
        @unlink($path);

        self::assertSame(['first', 'second'], $comments);
    }

    public function testMissingFileIsEmpty(): void
    {
        self::assertSame([], (new ReadAgentComments())->execute('/missing/ensemble-comments'));
    }
}
