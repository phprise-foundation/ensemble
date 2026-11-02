<?php

declare(strict_types=1);

namespace App\Domain\Team;

use App\Domain\Exception\ValidationException;

final readonly class Language
{
    private const LABELS = [
        'pt-BR' => 'Portuguese (Brazil)',
        'en' => 'English',
        'es' => 'Spanish',
    ];

    public function __construct(private string $value)
    {
        if (! isset(self::LABELS[$value])) {
            throw new ValidationException('language', 'Language must be pt-BR, en or es.');
        }
    }

    public static function default(): self
    {
        return new self('pt-BR');
    }

    public function value(): string
    {
        return $this->value;
    }

    public function label(): string
    {
        return self::LABELS[$this->value];
    }
}
