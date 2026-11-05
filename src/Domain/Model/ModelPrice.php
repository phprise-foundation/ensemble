<?php

declare(strict_types=1);

namespace App\Domain\Model;

use App\Domain\Exception\ValidationException;

final readonly class ModelPrice
{
    private const MICROS_PER_MILLION = 1_000_000;
    private const MICROS_PER_USD = 1_000_000;

    public function __construct(
        private int $cachedInputMicros,
        private int $inputMicros,
        private int $outputMicros,
    ) {
        if ($cachedInputMicros < 0 || $inputMicros < 0 || $outputMicros < 0) {
            throw new ValidationException('price', 'Model prices must not be negative.');
        }
    }

    public function cachedInputMicros(): int
    {
        return $this->cachedInputMicros;
    }

    public function inputMicros(): int
    {
        return $this->inputMicros;
    }

    public function outputMicros(): int
    {
        return $this->outputMicros;
    }

    public function costMicros(int $inputTokens, int $outputTokens, int $cachedTokens, int $reasoningTokens = 0): int
    {
        $micros = $inputTokens * $this->inputMicros
            + ($outputTokens + $reasoningTokens) * $this->outputMicros
            + $cachedTokens * $this->cachedInputMicros;

        return intdiv($micros + intdiv(self::MICROS_PER_MILLION, 2), self::MICROS_PER_MILLION);
    }

    public function toUsd(int $micros): float
    {
        return $micros / self::MICROS_PER_USD;
    }
}
