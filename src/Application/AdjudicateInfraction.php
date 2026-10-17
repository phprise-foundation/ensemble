<?php

declare(strict_types=1);

namespace App\Application;

use App\Domain\Contract\InfractionRepository;
use App\Domain\Exception\InfractionNotFoundException;
use App\Domain\Infraction\Infraction;
use App\Domain\Infraction\InfractionId;
use App\Domain\Infraction\InfractionStatus;
use DateTimeImmutable;

final readonly class AdjudicateInfraction
{
    public function __construct(private InfractionRepository $infractions)
    {
    }

    public function execute(InfractionId $id, InfractionStatus $status, string $adjudicatedBy): Infraction
    {
        $infraction = $this->find($id);
        $infraction->adjudicate($status, $adjudicatedBy, new DateTimeImmutable());
        $this->infractions->save($infraction);

        return $infraction;
    }

    private function find(InfractionId $id): Infraction
    {
        return $this->infractions->find($id) ?? throw InfractionNotFoundException::forId($id);
    }
}
