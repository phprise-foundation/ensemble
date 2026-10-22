<?php

declare(strict_types=1);

namespace App\Domain\Contract\Tenancy;

interface TokenGenerator
{
    public function generate(): string;
}
