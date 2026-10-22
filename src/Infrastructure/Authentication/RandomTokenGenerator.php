<?php

declare(strict_types=1);

namespace App\Infrastructure\Authentication;

use App\Domain\Contract\Tenancy\TokenGenerator;
use Override;

final readonly class RandomTokenGenerator implements TokenGenerator
{
    #[Override]
    public function generate(): string
    {
        return bin2hex(random_bytes(32));
    }
}
