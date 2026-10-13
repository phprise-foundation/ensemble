<?php

declare(strict_types=1);

namespace App\Domain\Workflow;

use InvalidArgumentException;
use Phprise\Common\ValueObject\StringObject;

final class Board
{
    private StringObject $name;

    public function __construct(string $name, private int $agingHours)
    {
        $this->name = new StringObject($name);
    }

    public function name(): string
    {
        return $this->name->getValue();
    }

    public function agingHours(): int
    {
        return $this->agingHours;
    }

    public function rename(string $name): void
    {
        $this->name = new StringObject($name);
    }

    public function withAgingWindow(int $hours): void
    {
        if ($hours < 1) {
            throw new InvalidArgumentException('Aging window must be at least one hour.');
        }
        $this->agingHours = $hours;
    }
}
