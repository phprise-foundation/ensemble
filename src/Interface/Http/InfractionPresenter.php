<?php

declare(strict_types=1);

namespace App\Interface\Http;

use App\Domain\Infraction\Infraction;

final class InfractionPresenter
{
    /** @return array<array-key, mixed> */
    public function present(Infraction $infraction): array
    {
        return [
            'id' => $infraction->id()->toInt(),
            'item_id' => $infraction->itemId()->toInt(),
            'infractor' => [
                'agent' => $infraction->infractorAgent(),
                'role' => $infraction->infractorRole(),
                'model' => $infraction->infractorModel(),
            ],
            'detected_by' => $infraction->detectedBy(),
            'cause' => $infraction->cause()->value,
            'severity' => $infraction->severity()->value,
            'evidence' => $infraction->evidence(),
            'blocked' => $infraction->blocked(),
            'status' => $infraction->status()->value,
            'adjudicated_by' => $infraction->adjudicatedBy(),
            'created_at' => $infraction->createdAt()->format(DATE_ATOM),
            'resolved_at' => $infraction->resolvedAt()?->format(DATE_ATOM),
        ];
    }

    /**
     * @param Infraction[] $infractions
     * @return list<array<array-key, mixed>>
     */
    public function presentMany(array $infractions): array
    {
        return array_values(array_map(fn(Infraction $infraction): array => $this->present($infraction), $infractions));
    }
}
