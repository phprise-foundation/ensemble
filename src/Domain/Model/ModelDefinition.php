<?php

declare(strict_types=1);

namespace App\Domain\Model;

use App\Domain\Exception\ValidationException;

final readonly class ModelDefinition
{
    public function __construct(
        private ModelId $id,
        private string $name,
        private ModelPrice $price,
        private InternalModel $internal,
    ) {
        if (trim($name) === '' || strlen($name) > 120) {
            throw new ValidationException('models', 'A model name is required (at most 120 characters).');
        }
    }

    public function id(): ModelId
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function price(): ModelPrice
    {
        return $this->price;
    }

    public function internal(): InternalModel
    {
        return $this->internal;
    }
}
