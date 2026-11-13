<?php

declare(strict_types=1);

namespace App\Domain\Contract\Tenancy;

interface UnitOfWork
{
    public function run(callable $operation): mixed;
}
