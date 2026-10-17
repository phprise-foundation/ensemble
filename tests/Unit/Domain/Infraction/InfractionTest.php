<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Infraction;

use App\Domain\Board\ItemId;
use App\Domain\Exception\InvalidInfractionException;
use App\Domain\Infraction\Cause;
use App\Domain\Infraction\Infraction;
use App\Domain\Infraction\InfractionId;
use App\Domain\Infraction\InfractionStatus;
use App\Domain\Infraction\Severity;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class InfractionTest extends TestCase
{
    private function infraction(): Infraction
    {
        return new Infraction(
            new InfractionId(1),
            new ItemId(2),
            'Kaio',
            'coder',
            'm/1',
            'Alexya',
            Cause::Bug,
            Severity::Major,
            'the suite is red',
            true,
            new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
        );
    }

    public function testAdjudicatesADeclaredInfraction(): void
    {
        $infraction = $this->infraction();

        $infraction->adjudicate(InfractionStatus::Upheld, 'Alan', new DateTimeImmutable());

        self::assertSame(InfractionStatus::Upheld, $infraction->status());
        self::assertSame('Alan', $infraction->adjudicatedBy());
        self::assertNotNull($infraction->resolvedAt());
    }

    public function testRejectsADeclaredVerdict(): void
    {
        $this->expectException(InvalidInfractionException::class);

        $this->infraction()->adjudicate(InfractionStatus::Declared, 'Alan', new DateTimeImmutable());
    }

    public function testRejectsASecondAdjudication(): void
    {
        $infraction = $this->infraction();
        $infraction->adjudicate(InfractionStatus::Upheld, 'Alan', new DateTimeImmutable());

        $this->expectException(InvalidInfractionException::class);
        $infraction->adjudicate(InfractionStatus::Dismissed, 'Alan', new DateTimeImmutable());
    }

    public function testRejectsTheInfractorAsAdjudicator(): void
    {
        $this->expectException(InvalidInfractionException::class);

        $this->infraction()->adjudicate(InfractionStatus::Upheld, 'Kaio', new DateTimeImmutable());
    }

    public function testRejectsTheDetectorAsAdjudicator(): void
    {
        $this->expectException(InvalidInfractionException::class);

        $this->infraction()->adjudicate(InfractionStatus::Upheld, 'Alexya', new DateTimeImmutable());
    }
}
