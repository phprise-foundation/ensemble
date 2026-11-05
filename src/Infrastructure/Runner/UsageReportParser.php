<?php

declare(strict_types=1);

namespace App\Infrastructure\Runner;

use App\Domain\Runner\ModelUsage;
use App\Domain\Runner\UsagePlan;
use App\Domain\Runner\UsageReport;

final readonly class UsageReportParser
{
    private const MICROS_PER_USD = 1_000_000;

    public function parse(string $path): ?UsageReport
    {
        $raw = is_file($path) ? file_get_contents($path) : false;
        if (! is_string($raw)) {
            return null;
        }
        $report = json_decode($raw, true);
        if (! is_array($report)) {
            return null;
        }
        $usage = $this->mapping($report, 'usage');
        if ($usage === null) {
            return null;
        }
        $byModel = $this->mapping($usage, 'by_model');
        if ($byModel !== null && $byModel !== []) {
            return $this->fromModels($byModel);
        }
        $total = $this->mapping($usage, 'total');
        if ($total === null) {
            return null;
        }

        return new UsageReport([$this->entry($this->text($report, 'model', 'unknown'), $total)]);
    }

    /**
     * @param array<array-key, mixed> $data
     * @return array<array-key, mixed>|null
     */
    private function mapping(array $data, string $key): ?array
    {
        if (! isset($data[$key]) || ! is_array($data[$key])) {
            return null;
        }

        return $data[$key];
    }

    /** @param array<array-key, mixed> $data */
    private function text(array $data, string $key, string $fallback): string
    {
        return isset($data[$key]) && is_string($data[$key]) ? $data[$key] : $fallback;
    }

    /** @param array<array-key, mixed> $byModel */
    private function fromModels(array $byModel): ?UsageReport
    {
        /** @var array<array-key, array<array-key, mixed>> $models */
        $models = array_filter($byModel, is_array(...));
        $entries = [];
        foreach ($models as $model => $totals) {
            $entries[] = $this->entry((string) $model, $totals);
        }

        return $entries === [] ? null : new UsageReport($entries);
    }

    /** @param array<array-key, mixed> $totals */
    private function entry(string $model, array $totals): ModelUsage
    {
        return new ModelUsage(
            $model,
            str_starts_with($model, 'free-') ? UsagePlan::Free : UsagePlan::Paid,
            $this->int($totals, 'requests'),
            $this->int($totals, 'tokens_input'),
            $this->int($totals, 'tokens_output'),
            $this->int($totals, 'tokens_reasoning'),
            $this->int($totals, 'tokens_cache_read'),
            $this->int($totals, 'tokens_cache_write'),
            (int) round($this->float($totals, 'cost') * (float) self::MICROS_PER_USD),
        );
    }

    /** @param array<array-key, mixed> $data */
    private function int(array $data, string $key): int
    {
        if (! isset($data[$key]) || ! is_numeric($data[$key])) {
            return 0;
        }

        return (int) $data[$key];
    }

    /** @param array<array-key, mixed> $data */
    private function float(array $data, string $key): float
    {
        if (! isset($data[$key]) || ! is_numeric($data[$key])) {
            return 0.0;
        }

        return (float) $data[$key];
    }
}
