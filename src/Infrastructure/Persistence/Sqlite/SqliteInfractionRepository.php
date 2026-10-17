<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Sqlite;

use App\Domain\Board\ItemId;
use App\Domain\Contract\InfractionRepository;
use App\Domain\Infraction\Cause;
use App\Domain\Infraction\Infraction;
use App\Domain\Infraction\InfractionId;
use App\Domain\Infraction\InfractionStatus;
use App\Domain\Infraction\Severity;
use DateTimeImmutable;
use Override;
use PDO;

final class SqliteInfractionRepository implements InfractionRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    #[Override]
    public function nextId(): InfractionId
    {
        $max = (int) $this->pdo->query('SELECT COALESCE(MAX(id), 0) FROM infractions')->fetchColumn();

        return new InfractionId($max + 1);
    }

    #[Override]
    public function add(Infraction $infraction): void
    {
        $this->pdo->prepare(
            'INSERT INTO infractions (id, item_id, infractor_agent, infractor_role, infractor_model, detected_by, cause, severity, evidence, blocked, status, adjudicated_by, created_at, resolved_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        )->execute($this->values($infraction));
    }

    #[Override]
    public function save(Infraction $infraction): void
    {
        $this->pdo->prepare(
            'UPDATE infractions SET status = ?, adjudicated_by = ?, resolved_at = ? WHERE id = ?',
        )->execute([
            $infraction->status()->value,
            $infraction->adjudicatedBy(),
            $infraction->resolvedAt()?->format(DATE_ATOM),
            $infraction->id()->toInt(),
        ]);
    }

    #[Override]
    public function find(InfractionId $id): ?Infraction
    {
        $statement = $this->pdo->prepare('SELECT * FROM infractions WHERE id = ?');
        $statement->execute([$id->toInt()]);
        /** @var array<array-key, mixed>|false $row */
        $row = $statement->fetch();

        return is_array($row) ? $this->fromRow($row) : null;
    }

    #[Override]
    public function forItem(ItemId $itemId): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM infractions WHERE item_id = ? ORDER BY id');
        $statement->execute([$itemId->toInt()]);
        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll();

        return array_map(fn(array $row): Infraction => $this->fromRow($row), $rows);
    }

    /** @return list<mixed> */
    private function values(Infraction $infraction): array
    {
        return [
            $infraction->id()->toInt(),
            $infraction->itemId()->toInt(),
            $infraction->infractorAgent(),
            $infraction->infractorRole(),
            $infraction->infractorModel(),
            $infraction->detectedBy(),
            $infraction->cause()->value,
            $infraction->severity()->value,
            $infraction->evidence(),
            $infraction->blocked() ? 1 : 0,
            $infraction->status()->value,
            $infraction->adjudicatedBy(),
            $infraction->createdAt()->format(DATE_ATOM),
            $infraction->resolvedAt()?->format(DATE_ATOM),
        ];
    }

    /** @param array<array-key, mixed> $row */
    private function fromRow(array $row): Infraction
    {
        $infraction = new Infraction(
            new InfractionId((int) $row['id']),
            new ItemId((int) $row['item_id']),
            (string) $row['infractor_agent'],
            (string) $row['infractor_role'],
            (string) $row['infractor_model'],
            (string) $row['detected_by'],
            Cause::from((string) $row['cause']),
            Severity::from((string) $row['severity']),
            (string) $row['evidence'],
            (bool) $row['blocked'],
            new DateTimeImmutable((string) $row['created_at']),
        );
        $status = InfractionStatus::from((string) $row['status']);
        if ($status !== InfractionStatus::Declared) {
            $infraction->restoreAdjudication(
                $status,
                (string) $row['adjudicated_by'],
                $this->resolvedAtFrom($row['resolved_at'] ?? null),
            );
        }

        return $infraction;
    }

    private function resolvedAtFrom(mixed $value): ?DateTimeImmutable
    {
        return is_string($value) && $value !== '' ? new DateTimeImmutable($value) : null;
    }
}
