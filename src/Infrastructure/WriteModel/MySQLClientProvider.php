<?php

namespace SaaSFormation\Framework\MySQLBasedWriteModel\Infrastructure\WriteModel;

use Psr\Log\LoggerInterface;
use SaaSFormation\Framework\Contracts\Common\Identity\UUIDFactoryInterface;
use SaaSFormation\Framework\Contracts\Infrastructure\EnvVarsManagerInterface;
use SaaSFormation\Framework\Contracts\Infrastructure\WriteModel\ClientProviderInterface;

readonly class MySQLClientProvider implements ClientProviderInterface
{
    public function __construct(private EnvVarsManagerInterface $envVarsManager)
    {
    }

    public function provide(LoggerInterface $logger, UUIDFactoryInterface $UUIDFactory): MySQLClient
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

        return new MySQLClient($mysqlUri, $mysqlUsername, $mysqlPassword, $logger, $UUIDFactory);
    }
}