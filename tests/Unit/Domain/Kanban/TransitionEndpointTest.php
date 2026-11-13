<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Kanban;

use App\Domain\Exception\ValidationException;
use App\Domain\Kanban\StatusId;
use App\Domain\Kanban\TransitionEndpoint;
use PHPUnit\Framework\TestCase;

final class TransitionEndpointTest extends TestCase
{
    public function testAnyEndpoint(): void
    {
        $endpoint = TransitionEndpoint::fromString('any');

        self::assertTrue($endpoint->isAny());
        self::assertSame('*', $endpoint->value());
        self::assertNull($endpoint->statusId());
    }

    public function testConcreteEndpoint(): void
    {
        $id = new StatusId(str_repeat('a', 32));
        $endpoint = TransitionEndpoint::fromString($id->value());

        self::assertFalse($endpoint->isAny());
        self::assertSame($id->value(), $endpoint->value());
        self::assertSame($id->value(), $endpoint->statusId()?->value());
    }

    public function testRejectsAnInvalidEndpoint(): void
    {
        $this->expectException(ValidationException::class);
        TransitionEndpoint::fromString('not-a-status');
    }
}
