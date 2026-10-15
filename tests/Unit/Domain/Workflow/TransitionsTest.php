<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Workflow;

use App\Domain\Workflow\ColumnId;
use App\Domain\Workflow\Transitions;
use PHPUnit\Framework\TestCase;

final class TransitionsTest extends TestCase
{
    public function testAllowsOnlyConfiguredTargets(): void
    {
        $transitions = new Transitions([new ColumnId('coding'), new ColumnId('reviewing')]);

        self::assertCount(2, $transitions);
        self::assertTrue($transitions->allows(new ColumnId('coding')));
        self::assertTrue($transitions->allows(new ColumnId('reviewing')));
        self::assertFalse($transitions->allows(new ColumnId('done')));
    }

    public function testSoleReturnsTheOnlyTarget(): void
    {
        self::assertNull((new Transitions())->sole());
        self::assertNull((new Transitions([new ColumnId('a'), new ColumnId('b')]))->sole());
        self::assertSame('coding', (new Transitions([new ColumnId('coding')]))->sole()?->value());
    }
}
