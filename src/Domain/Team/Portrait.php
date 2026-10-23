<?php

declare(strict_types=1);

namespace App\Domain\Team;

use App\Domain\Exception\ValidationException;

final readonly class Portrait
{
    private const MAX_BYTES = 2_097_152;

    public function __construct(private string $value)
    {
        if ($value === '') {
            return;
        }

        if (!preg_match('/^data:image\/(png|jpeg|webp);base64,[A-Za-z0-9+\/=]+$/', $value)) {
            throw new ValidationException('portrait', 'Portrait must be a base64 data URL for png, jpeg or webp images.');
        }

        $raw = $value;
        $comma = strpos($raw, ',');
        if ($comma === false) {
            throw new ValidationException('portrait', 'Portrait must be a base64 data URL for png, jpeg or webp images.');
        }
        $decoded = base64_decode(substr($raw, $comma + 1), true);
        if ($decoded === false || strlen($decoded) > self::MAX_BYTES) {
            throw new ValidationException('portrait', 'Portrait must decode to at most 2 MiB.');
        }
    }

    public static function none(): self
    {
        return new self('');
    }

    public function value(): string
    {
        return $this->value;
    }

    public function isPresent(): bool
    {
        return $this->value !== '';
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
