<?php

namespace SaaSFormation\Framework\MySQLBasedWriteModel\Infrastructure\WriteModel;

use SaaSFormation\Framework\SharedKernel\Application\Messages\CommandInterface;
use SaaSFormation\Framework\SharedKernel\Common\Identity\IdInterface;
use SaaSFormation\Framework\SharedKernel\Domain\AbstractAggregate;
use SaaSFormation\Framework\SharedKernel\Domain\Messages\DomainEventInterface;
use SaaSFormation\Framework\SharedKernel\Domain\WriteModel\RepositoryInterface;

readonly class MySQLBasedRepository implements RepositoryInterface
{
    private MySQLClient $client;

    public function __construct(private MySQLClientProvider $mySQLClientProvider)
    {
        $this->client = $this->mySQLClientProvider->provide();
    }

    public function save(DomainEventInterface $domainEvent): void
    {
        $this->client->save($domainEvent);
    }

    public function saveCommand(CommandInterface $command): void
    {
        $this->client->saveCommand($command);
    }

    public function hasAggregate(IdInterface $id): bool
    {
        return count($this->client->events($id)) > 0;
    }
}