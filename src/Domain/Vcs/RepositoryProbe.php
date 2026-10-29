<?php

declare(strict_types=1);

namespace App\Domain\Vcs;

interface RepositoryProbe
{
    public function probe(string $url, Provider $provider, ?string $token): ProbeResult;
}
