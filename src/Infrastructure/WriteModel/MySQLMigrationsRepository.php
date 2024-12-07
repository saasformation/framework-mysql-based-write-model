<?php

namespace SaaSFormation\Framework\MySQLBasedWriteModel\Infrastructure\WriteModel;

use Psr\Log\LoggerInterface;
use SaaSFormation\Framework\Contracts\Common\Identity\UUIDFactoryInterface;

class MySQLMigrationsRepository
{
    private MySQLClient $mySQLClient;

    public function __construct(MySQLClientProvider $mySQLClientProvider, LoggerInterface $logger, UUIDFactoryInterface $UUIDFactory)
    {
        $this->mySQLClient = $mySQLClientProvider->provide($logger, $UUIDFactory);
    }

    public function migrationsTableExists(): bool
    {
        return $this->mySQLClient->migrationsTableExists();
    }

    public function createMigrationsTable(): void
    {
        $this->mySQLClient->createMigrationsTable();
    }

    public function isMigrationExecuted(string $name): bool
    {
        return $this->mySQLClient->isMigrationExecuted($name);
    }

    public function saveMigrationAsExecuted(string $name): void
    {
        $this->mySQLClient->saveMigrationAsExecuted($name);
    }

    public function runMigration(string $name, string $migration): void
    {
        $this->mySQLClient->raw($migration);
        $this->mySQLClient->saveMigrationAsExecuted($name);
    }
}