<?php

namespace SaaSFormation\Framework\MySQLBasedWriteModel\Infrastructure\WriteModel;

use Psr\Log\LoggerInterface;
use SaaSFormation\Framework\Contracts\Common\Identity\IdInterface;
use SaaSFormation\Framework\Contracts\Common\Identity\UUIDFactoryInterface;
use SaaSFormation\Framework\Contracts\Domain\Aggregate;
use SaaSFormation\Framework\Contracts\Infrastructure\WriteModel\ClientInterface;

class MySQLClient implements ClientInterface
{
    private \PDO $pdo;
    private int $transactionCounter = 0;

    public function __construct(
        readonly string $mySQLUri,
        readonly string $mySQLUsername,
        readonly string $mySQLPassword,
        private readonly LoggerInterface $logger,
        private readonly UUIDFactoryInterface $uuidFactory
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

    public function save(Aggregate $aggregate): void
    {
        foreach ($aggregate->eventStream()->events() as $event) {
            $this->logTryingToPush($aggregate->id());
            $this->beginTransaction();

            try {
                $this->pdo->prepare(
                    "INSERT INTO eventstore (id, aggregate_id, aggregate_code, event_code, event_version, event_data, created_at) values (:id, :aggregate_id, :aggregate_code, :event_code, :event_version, :event_data, :created_at)"
                )->execute([
                    'id' => $event->id() ? $event->id()->humanReadable() : $this->uuidFactory->generate()->humanReadable(),
                    'aggregate_id' => $aggregate->id()->humanReadable(),
                    'aggregate_code' => $aggregate->code(),
                    'event_code' => $event->code(),
                    'event_version' => $event->version(),
                    'event_data' => json_encode($event->toArray()),
                    'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.u'),
                ]);

                $this->commitTransaction();
                $this->logPushed($aggregate->id());
            } catch (\Throwable $e) {
                $this->logFailedToPush($e, $aggregate->id());
                $this->rollbackTransaction();
                throw new \Exception($e->getMessage());
            }
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