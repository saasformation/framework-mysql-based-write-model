<?php

namespace SaaSFormation\Framework\MySQLBasedWriteModel\Infrastructure\WriteModel;

use SaaSFormation\Framework\SharedKernel\Common\Identity\IdInterface;
use SaaSFormation\Framework\SharedKernel\Domain\AbstractAggregate;
use SaaSFormation\Framework\SharedKernel\Domain\WriteModel\RepositoryInterface;

readonly class MySQLBasedRepository implements RepositoryInterface
{
    private MySQLClient $client;

    public function __construct(private MySQLClientProvider $mySQLClientProvider)
    {
        $this->client = $this->mySQLClientProvider->provide();
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