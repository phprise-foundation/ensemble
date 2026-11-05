<?php

declare(strict_types=1);

namespace App\Infrastructure\Model;

use App\Domain\Exception\ValidationException;
use App\Domain\Model\InternalModel;
use App\Domain\Model\ModelCatalog;
use App\Domain\Model\ModelDefinition;
use App\Domain\Model\ModelId;
use App\Domain\Model\ModelPrice;
use Symfony\Component\Yaml\Yaml;

final readonly class YamlModelCatalog
{
    private const MICROS_PER_USD = 1_000_000;

    public function __construct(private string $path)
    {
    }

    public function load(): ModelCatalog
    {
        if (! is_file($this->path)) {
            throw new ValidationException('models', sprintf('The models file "%s" does not exist.', $this->path));
        }
        $data = Yaml::parseFile($this->path);
        if (! is_array($data)) {
            throw new ValidationException('models', 'The models file must be a mapping.');
        }
        $entries = $data['models'] ?? null;
        if (! is_array($entries) || $entries === []) {
            throw new ValidationException('models', 'The models file needs at least one model.');
        }
        $models = [];
        foreach (array_keys($entries) as $id) {
            $models[(string) $id] = $this->definition((string) $id, $entries[$id]);
        }

        return new ModelCatalog(new ModelId($this->text($data, 'default')), $models);
    }

    private function definition(string $id, mixed $entry): ModelDefinition
    {
        if (! is_array($entry)) {
            throw new ValidationException('models', sprintf('Model "%s" must be a mapping.', $id));
        }

        return new ModelDefinition(
            new ModelId($id),
            $this->text($entry, 'name', $id),
            $this->price($id, $entry['price_per_million'] ?? null),
            $this->internal($id, $entry['internal'] ?? null),
        );
    }

    private function price(string $id, mixed $entry): ModelPrice
    {
        if (! is_array($entry)) {
            throw new ValidationException('models', sprintf('Model "%s" needs a price_per_million mapping.', $id));
        }

        return new ModelPrice(
            $this->micros($entry, 'cached_input_tokens', $id),
            $this->micros($entry, 'input_tokens', $id),
            $this->micros($entry, 'output_tokens', $id),
        );
    }

    private function internal(string $id, mixed $entry): InternalModel
    {
        if (! is_array($entry)) {
            throw new ValidationException('models', sprintf('Model "%s" needs an internal mapping.', $id));
        }

        return new InternalModel(
            $this->text($entry, 'model', $id),
            $this->optional($entry, 'fallback_model'),
            $this->optional($entry, 'subagent'),
            $this->optional($entry, 'fallback_subagent'),
        );
    }

    /** @param array<array-key, mixed> $entry */
    private function text(array $entry, string $key, string $id = ''): string
    {
        $value = $entry[$key] ?? null;
        if (! is_string($value) || trim($value) === '') {
            throw new ValidationException(
                'models',
                $id === ''
                    ? sprintf('The "%s" key is required.', $key)
                    : sprintf('Model "%s" needs a non-empty "%s".', $id, $key),
            );
        }

        return trim($value);
    }

    /** @param array<array-key, mixed> $entry */
    private function optional(array $entry, string $key): string
    {
        if (! isset($entry[$key]) || ! is_string($entry[$key])) {
            return '';
        }

        return trim($entry[$key]);
    }

    /** @param array<array-key, mixed> $entry */
    private function micros(array $entry, string $key, string $id): int
    {
        if (! isset($entry[$key]) || ! is_numeric($entry[$key])) {
            throw new ValidationException(
                'models',
                sprintf('Model "%s" needs a non-negative numeric "%s".', $id, $key),
            );
        }
        $number = (float) $entry[$key];
        if ($number < 0.0) {
            throw new ValidationException(
                'models',
                sprintf('Model "%s" needs a non-negative numeric "%s".', $id, $key),
            );
        }

        return (int) round($number * (float) self::MICROS_PER_USD);
    }
}
