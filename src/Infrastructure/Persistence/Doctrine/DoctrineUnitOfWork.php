<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine;

use App\Domain\Contract\Tenancy\UnitOfWork;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Throwable;

final readonly class DoctrineUnitOfWork implements UnitOfWork
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    #[Override]
    public function run(callable $operation): mixed
    {
        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();
        try {
            /** @var mixed $result */
            $result = $operation();
            $connection->commit();

            return $result;
        } catch (Throwable $error) {
            $connection->rollBack();

            throw $error;
        }
    }
}
