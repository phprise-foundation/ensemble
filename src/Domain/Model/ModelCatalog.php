<?php

declare(strict_types=1);

namespace App\Domain\Model;

use App\Domain\Exception\ValidationException;

final readonly class ModelCatalog
{
    /** @param array<string, ModelDefinition> $models */
    public function __construct(private ModelId $default, private array $models)
    {
        if (! isset($models[$default->value()])) {
            throw new ValidationException(
                'models',
                sprintf('The default model "%s" is not in the catalog.', $default->value()),
            );
        }
    }

    public function defaultId(): ModelId
    {
        return $this->default;
    }

    public function find(ModelId $id): ?ModelDefinition
    {
        return $this->models[$id->value()] ?? null;
    }

    public function resolve(string $id): ModelDefinition
    {
        $definition = $this->models[trim($id)] ?? null;

        return $definition ?? $this->defaultDefinition();
    }

    /** @return list<ModelDefinition> */
    public function all(): array
    {
        return array_values($this->models);
    }

    /** @return list<string> */
    public function ids(): array
    {
        return array_keys($this->models);
    }

    private function defaultDefinition(): ModelDefinition
    {
        return $this->models[$this->default->value()];
    }
}
