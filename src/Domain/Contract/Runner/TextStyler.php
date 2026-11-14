<?php

declare(strict_types=1);

namespace App\Domain\Contract\Runner;

use App\Domain\Team\Agent;
use App\Domain\Team\Profile;

interface TextStyler
{
    /**
     * @param list<string> $texts
     * @return list<string>
     */
    public function styleMany(array $texts, Agent $agent, ?Profile $profile): array;
}
