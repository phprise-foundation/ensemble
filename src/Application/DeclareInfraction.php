<?php

declare(strict_types=1);

namespace App\Application;

use App\Domain\Board\ItemId;
use App\Domain\Contract\InfractionRepository;
use App\Domain\Contract\ItemRepository;
use App\Domain\Exception\InvalidInfractionException;
use App\Domain\Exception\ItemNotFoundException;
use App\Domain\Infraction\Cause;
use App\Domain\Infraction\Infraction;
use App\Domain\Infraction\Severity;
use DateTimeImmutable;

final readonly class DeclareInfraction
{
    public function __construct(
        private ItemRepository $items,
        private InfractionRepository $infractions,
    ) {
    }

    public function execute(
        ItemId $itemId,
        string $infractorAgent,
        string $infractorRole,
        string $infractorModel,
        string $detectedBy,
        Cause $cause,
        Severity $severity,
        string $evidence,
        bool $blocked,
    ): Infraction {
        $this->items->find($itemId) ?? throw ItemNotFoundException::forId($itemId);
        if ($evidence === '') {
            throw InvalidInfractionException::evidenceRequired();
        }
        if ($detectedBy !== '' && $detectedBy === $infractorAgent) {
            throw InvalidInfractionException::selfInfraction();
        }
        $infraction = new Infraction(
            $this->infractions->nextId(),
            $itemId,
            $infractorAgent,
            $infractorRole,
            $infractorModel,
            $detectedBy,
            $cause,
            $severity,
            $evidence,
            $blocked,
            new DateTimeImmutable(),
        );
        $this->infractions->add($infraction);

        return $infraction;
    }
}
