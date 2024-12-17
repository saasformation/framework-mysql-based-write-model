<?php

namespace SaaSFormation\Framework\MySQLBasedWriteModel\Infrastructure\WriteModel;

use Assert\Assert;
use Psr\Log\LoggerInterface;
use SaaSFormation\Framework\Contracts\Infrastructure\WriteModel\ClientInterface;
use SaaSFormation\Framework\SharedKernel\Application\Messages\CommandInterface;
use SaaSFormation\Framework\SharedKernel\Common\Identity\IdInterface;
use SaaSFormation\Framework\SharedKernel\Common\Identity\UUIDFactoryInterface;
use SaaSFormation\Framework\SharedKernel\Domain\AbstractAggregate;
use SaaSFormation\Framework\SharedKernel\Domain\Messages\DomainEventInterface;

class MySQLClient implements ClientInterface
{
    private \PDO $pdo;
    private int $transactionCounter = 0;

    public function __construct(
        readonly string                  $mySQLUri,
        readonly string                  $mySQLUsername,
        readonly string                  $mySQLPassword,
        private readonly LoggerInterface $logger
    )
    {
        $this->pdo = new \PDO($mySQLUri, $mySQLUsername, $mySQLPassword);
    }

    public function beginTransaction(): void
    {
        if ($this->transactionCounter === 0) {
            $this->pdo->beginTransaction();
        }
        $this->transactionCounter++;
    }

    public function commitTransaction(): void
    {
        if ($this->transactionCounter === 0) {
            throw new \Exception("No active transaction to commit.");
        }

        $this->transactionCounter--;

        if ($this->transactionCounter === 0) {
            $this->pdo->commit();
        }
    }

    public function rollbackTransaction(): void
    {
        if ($this->transactionCounter === 0) {
            throw new \Exception("No active transaction to rollback.");
        }

        $this->transactionCounter--;

        if ($this->transactionCounter === 0) {
            $this->pdo->rollBack();
        }
    }

    public function save(DomainEventInterface $domainEvent): void
    {
        $this->logTryingToPush($domainEvent->getAggregateId());
        $this->beginTransaction();

        Assert::that($domainEvent->getDomainEventId())->isInstanceOf(IdInterface::class);
        Assert::that($domainEvent->getRequestId())->isInstanceOf(IdInterface::class);
        Assert::that($domainEvent->getCorrelationId())->isInstanceOf(IdInterface::class);
        Assert::that($domainEvent->getGeneratorCommandId())->isInstanceOf(IdInterface::class);

        try {
            $this->pdo->prepare(
                "INSERT INTO eventstore (
                        id, aggregate_id, aggregate_code, event_code, event_version, event_data, request_id, correlation_id, generator_command_id, created_at
                        ) values (
                                  :id, :aggregate_id, :aggregate_code, :event_code, :event_version, :event_data, :request_id, :correlation_id, :generator_command_id, :created_at
                      )"
            )->execute([
                'id' => $domainEvent->getDomainEventId()->humanReadable(),
                'aggregate_id' => $domainEvent->getAggregateId()->humanReadable(),
                'aggregate_code' => $domainEvent->getAggregateCode(),
                'event_code' => $domainEvent->getDomainEventCode(),
                'event_version' => $domainEvent->getDomainEventVersion(),
                'event_data' => json_encode($domainEvent->toArray()),
                'request_id' => $domainEvent->getRequestId()->humanReadable(),
                'correlation_id' => $domainEvent->getCorrelationId()->humanReadable(),
                'generator_command_id' => $domainEvent->getGeneratorCommandId()->humanReadable(),
                'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.u'),
            ]);

            $this->commitTransaction();
            $this->logPushed($domainEvent->getAggregateId());
        } catch (\Throwable $e) {
            $this->logFailedToPush($e, $domainEvent->getAggregateId());
            $this->rollbackTransaction();
            throw new \Exception($e->getMessage());
        }
    }

    public function saveCommand(CommandInterface $command): void
    {
        Assert::that($command->getCommandId())->isInstanceOf(IdInterface::class);
        Assert::that($command->getRequestId())->isInstanceOf(IdInterface::class);
        Assert::that($command->getCorrelationId())->isInstanceOf(IdInterface::class);
        Assert::that($command->getExecutorId())->isInstanceOf(IdInterface::class);

        try {
            $this->pdo->prepare(
                "INSERT INTO commandstore (
                        id, code, data, request_id, correlation_id, executor_id, status, created_at
                        ) values (
                                  :id, :code, :data, :request_id, :correlation_id, :executor_id, :status, :created_at
                      )"
            )->execute([
                'id' => $command->getCommandId()->humanReadable(),
                'code' => $command->getCommandCode(),
                'data' => json_encode($command->toArray()),
                'request_id' => $command->getRequestId()->humanReadable(),
                'correlation_id' => $command->getCorrelationId()->humanReadable(),
                'executor_id' => $command->getExecutorId()->humanReadable(),
                'status' => $command->getStatus()->value,
                'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.u'),
            ]);

        } catch (\Throwable $e) {
            throw new \Exception($e->getMessage());
        }
    }

    public function events(IdInterface $aggregateId): array
    {
        $query = $this->pdo->prepare("SELECT * FROM eventstore WHERE aggregate_id = :id");
        $query->execute([
            'id' => $aggregateId->humanReadable(),
        ]);

        $results = $query->fetchAll(\PDO::FETCH_ASSOC);
        $query->closeCursor();

        return $results;
    }

    public function migrationsTableExists(): bool
    {
        $stm = $this->pdo->prepare("SHOW TABLES LIKE 'migrations'");
        $stm->execute();

        $results = $stm->fetchAll(\PDO::FETCH_ASSOC);
        $stm->closeCursor();

        return !empty($results);
    }

    public function createMigrationsTable(): void
    {
        $stm = $this->pdo->prepare(
            "CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `execution_date` datetime(6) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `migration_name` (`migration_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
        );
        $stm->execute();
    }

    public function isMigrationExecuted(string $name): bool
    {
        $query = $this->pdo->prepare("SELECT * FROM migrations WHERE migration_name = :migration_name");
        $query->execute([
            'migration_name' => $name,
        ]);

        $results = count($query->fetchAll()) > 0;
        $query->closeCursor();

        return $results;
    }

    public function saveMigrationAsExecuted(string $name): void
    {
        $this->pdo->prepare("INSERT INTO migrations (migration_name, execution_date) VALUES (:migration_name, :execution_date)")->execute([
            'migration_name' => $name,
            'execution_date' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.u'),
        ]);
    }

    public function raw(string $query): void
    {
        $stm = $this->pdo->prepare($query);
        $stm->execute();
        $stm->closeCursor();
    }

    /**
     * @param IdInterface $aggregateId
     * @return void
     */
    protected function logTryingToPush(IdInterface $aggregateId): void
    {
        $this->logger->debug("Trying to push domain events to the event store", [
            "data" => [
                "aggregateId" => $aggregateId->humanReadable()
            ]
        ]);
    }

    /**
     * @param IdInterface $aggregateId
     * @return void
     */
    protected function logPushed(IdInterface $aggregateId): void
    {
        $this->logger->debug("Domain events pushed to the event store", [
            "data" => [
                "aggregateId" => $aggregateId->humanReadable()
            ]
        ]);
    }

    /**
     * @param \Throwable|\Exception $e
     * @param IdInterface $aggregateId
     * @return void
     */
    protected function logFailedToPush(\Throwable|\Exception $e, IdInterface $aggregateId): void
    {
        $this->logger->error("Domain events failed to push to the event store", [
            "error" => [
                "message" => $e->getMessage(),
                "file" => $e->getFile(),
                "line" => $e->getLine(),
            ],
            "data" => [
                "aggregateId" => $aggregateId->humanReadable()
            ]
        ]);
    }
}