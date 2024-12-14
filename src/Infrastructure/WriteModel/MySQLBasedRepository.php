<?php

namespace SaaSFormation\Framework\MySQLBasedWriteModel\Infrastructure\WriteModel;

use Psr\Log\LoggerInterface;
use SaaSFormation\Framework\SharedKernel\Common\Identity\IdInterface;
use SaaSFormation\Framework\SharedKernel\Common\Identity\UUIDFactoryInterface;
use SaaSFormation\Framework\SharedKernel\Domain\AbstractAggregate;
use SaaSFormation\Framework\SharedKernel\Domain\WriteModel\RepositoryInterface;

readonly class MySQLBasedRepository implements RepositoryInterface
{
    private MySQLClient $client;

    public function __construct(private MySQLClientProvider $mySQLClientProvider, private LoggerInterface $logger, private UUIDFactoryInterface $uuidFactory)
    {
        $this->client = $this->mySQLClientProvider->provide($this->logger, $this->uuidFactory);
    }

    public function save(AbstractAggregate $aggregate): void
    {
        $this->client->save($aggregate);
    }

    public function hasAggregate(IdInterface $id): bool
    {
        return count($this->client->events($id)) > 0;
    }
}