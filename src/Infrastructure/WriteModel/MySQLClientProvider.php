<?php

namespace SaaSFormation\Framework\MySQLBasedWriteModel\Infrastructure\WriteModel;

use Psr\Log\LoggerInterface;
use SaaSFormation\Framework\Contracts\Infrastructure\EnvVarsManagerInterface;
use SaaSFormation\Framework\Contracts\Infrastructure\WriteModel\ClientProviderInterface;
use SaaSFormation\Framework\SharedKernel\Common\Identity\UUIDFactoryInterface;

readonly class MySQLClientProvider implements ClientProviderInterface
{
    private MySQLClient $mySQLClient;

    public function __construct(private EnvVarsManagerInterface $envVarsManager, LoggerInterface $logger)
    {
        $mysqlUri = $this->envVarsManager->get('MYSQL_URI');
        $mysqlUsername = $this->envVarsManager->get('MYSQL_USERNAME');
        $mysqlPassword = $this->envVarsManager->get('MYSQL_PASSWORD');

        if(!is_string($mysqlUri)) {
            throw new \InvalidArgumentException('MYSQL_URI must be a string');
        }

        if(!is_string($mysqlUsername)) {
            throw new \InvalidArgumentException('MYSQL_USERNAME must be a string');
        }

        if(!is_string($mysqlPassword)) {
            throw new \InvalidArgumentException('MYSQL_PASSWORD must be a string');
        }

        $this->mySQLClient = new MySQLClient($mysqlUri, $mysqlUsername, $mysqlPassword, $logger);
    }

    public function provide(): MySQLClient
    {
        return $this->mySQLClient;
    }
}