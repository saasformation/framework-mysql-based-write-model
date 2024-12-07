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

    public function __construct(
        readonly string $mySQLUri,
        readonly string $mySQLUsername,
        readonly string $mySQLPassword,
        private readonly LoggerInterface $logger,
        private readonly UUIDFactoryInterface $uuidFactory
    )
    {
        $this->pdo = new \PDO($mySQLUri, $mySQLUsername, $mySQLPassword);
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    }

    public function beginTransaction(): void
    {
        $this->pdo->beginTransaction();
    }

    public function commitTransaction(): void
    {
        $this->pdo->commit();
    }

    public function rollbackTransaction(): void
    {
        $this->pdo->rollBack();
    }

    public function save(Aggregate $aggregate): void
    {
        foreach ($aggregate->eventStream()->events() as $event) {
            $this->logTryingToPush($aggregate->id());
            $this->pdo->beginTransaction();

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

                $this->pdo->commit();
                $this->logPushed($aggregate->id());
            } catch (\Throwable $e) {
                $this->logFailedToPush($e, $aggregate->id());
                $this->pdo->rollBack();
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

        return $query->fetchAll(\PDO::FETCH_ASSOC);
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