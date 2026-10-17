<?php

declare(strict_types=1);

namespace App\Domain\Infraction;

use App\Domain\Board\ItemId;
use App\Domain\Exception\InvalidInfractionException;
use DateTimeImmutable;
use Phprise\Common\ValueObject\StringObject;

final class Infraction
{
    private StringObject $infractorAgent;
    private StringObject $infractorRole;
    private StringObject $infractorModel;
    private StringObject $detectedBy;
    private StringObject $evidence;
    private StringObject $adjudicatedBy;
    private bool $blocked;
    private InfractionStatus $status;
    private ?DateTimeImmutable $resolvedAt;

    public function __construct(
        private readonly InfractionId $id,
        private readonly ItemId $itemId,
        string $infractorAgent,
        string $infractorRole,
        string $infractorModel,
        string $detectedBy,
        private readonly Cause $cause,
        private readonly Severity $severity,
        string $evidence,
        bool $blocked,
        private readonly DateTimeImmutable $createdAt,
    ) {
        $this->infractorAgent = new StringObject($infractorAgent);
        $this->infractorRole = new StringObject($infractorRole);
        $this->infractorModel = new StringObject($infractorModel);
        $this->detectedBy = new StringObject($detectedBy);
        $this->evidence = new StringObject($evidence);
        $this->adjudicatedBy = new StringObject('');
        $this->blocked = $blocked;
        $this->status = InfractionStatus::Declared;
        $this->resolvedAt = null;
    }

    public function id(): InfractionId
    {
        return $this->id;
    }

    public function itemId(): ItemId
    {
        return $this->itemId;
    }

    public function infractorAgent(): string
    {
        return $this->infractorAgent->getValue();
    }

    public function infractorRole(): string
    {
        return $this->infractorRole->getValue();
    }

    public function infractorModel(): string
    {
        return $this->infractorModel->getValue();
    }

    public function detectedBy(): string
    {
        return $this->detectedBy->getValue();
    }

    public function cause(): Cause
    {
        return $this->cause;
    }

    public function severity(): Severity
    {
        return $this->severity;
    }

    public function evidence(): string
    {
        return $this->evidence->getValue();
    }

    public function blocked(): bool
    {
        return $this->blocked;
    }

    public function status(): InfractionStatus
    {
        return $this->status;
    }

    public function adjudicatedBy(): string
    {
        return $this->adjudicatedBy->getValue();
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function resolvedAt(): ?DateTimeImmutable
    {
        return $this->resolvedAt;
    }

    public function adjudicate(InfractionStatus $status, string $adjudicatedBy, DateTimeImmutable $at): void
    {
        if ($status === InfractionStatus::Declared) {
            throw InvalidInfractionException::invalidVerdict();
        }
        if ($this->status !== InfractionStatus::Declared) {
            throw InvalidInfractionException::alreadyAdjudicated();
        }
        if ($adjudicatedBy === '' || $adjudicatedBy === $this->detectedBy->getValue() || $adjudicatedBy === $this->infractorAgent->getValue()) {
            throw InvalidInfractionException::impartiality();
        }
        $this->status = $status;
        $this->adjudicatedBy = new StringObject($adjudicatedBy);
        $this->resolvedAt = $at;
    }

    public function restoreAdjudication(InfractionStatus $status, string $adjudicatedBy, ?DateTimeImmutable $resolvedAt): void
    {
        $this->status = $status;
        $this->adjudicatedBy = new StringObject($adjudicatedBy);
        $this->resolvedAt = $resolvedAt;
    }
}
