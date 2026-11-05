<?php

declare(strict_types=1);

namespace App\Interface\Http;

use App\Domain\Model\ModelDefinition;

final class ModelPresenter
{
    /** @return array<string, mixed> */
    public function present(ModelDefinition $model): array
    {
        $price = $model->price();

        return [
            'id' => $model->id()->value(),
            'name' => $model->name(),
            'pricePerMillion' => [
                'cachedInputTokens' => round($price->toUsd($price->cachedInputMicros()), 6),
                'inputTokens' => round($price->toUsd($price->inputMicros()), 6),
                'outputTokens' => round($price->toUsd($price->outputMicros()), 6),
            ],
        ];
    }

    /**
     * @param list<ModelDefinition> $models
     * @return list<array<string, mixed>>
     */
    public function presentMany(array $models): array
    {
        return array_map(fn (ModelDefinition $model): array => $this->present($model), $models);
    }
}
