<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;

final readonly class DoctrineEntityManagerFactory
{
    public function __construct(private string $databaseUrl, private bool $devMode = true)
    {
    }

    public static function fromEnvironment(bool $test = false): self
    {
        $name = $test ? 'TEST_DATABASE_URL' : 'DATABASE_URL';
        $fallback = $test
            ? 'pgsql://phprise:phprise@127.0.0.1:5432/phprise_test'
            : 'pgsql://phprise:phprise@127.0.0.1:5432/phprise';

        return new self(self::env($name, $fallback), self::env('APP_ENV', 'dev') !== 'production');
    }

    public function create(): EntityManagerInterface
    {
        return $this->build();
    }

    public function createWithSchema(): EntityManagerInterface
    {
        $entityManager = $this->build();
        $this->schemaTool($entityManager)->updateSchema($this->metadata($entityManager));

        return $entityManager;
    }

    public function recreateSchema(): EntityManagerInterface
    {
        $entityManager = $this->build();
        $tool = $this->schemaTool($entityManager);
        $metadata = $this->metadata($entityManager);
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);

        return $entityManager;
    }

    private function build(): EntityManagerInterface
    {
        $config = ORMSetup::createAttributeMetadataConfig([__DIR__ . '/Entity'], $this->devMode);
        $config->enableNativeLazyObjects(true);
        $params = (new DsnParser())->parse($this->databaseUrl);
        $connection = DriverManager::getConnection($params, $config);

        return new EntityManager($connection, $config);
    }

    private function schemaTool(EntityManagerInterface $entityManager): SchemaTool
    {
        return new SchemaTool($entityManager);
    }

    /** @return list<\Doctrine\ORM\Mapping\ClassMetadata> */
    private function metadata(EntityManagerInterface $entityManager): array
    {
        return $entityManager->getMetadataFactory()->getAllMetadata();
    }

    private static function env(string $name, string $fallback): string
    {
        $value = getenv($name);

        return $value === false || $value === '' ? $fallback : $value;
    }
}
