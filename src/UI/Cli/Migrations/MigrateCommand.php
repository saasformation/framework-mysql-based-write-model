<?php

namespace SaaSFormation\Framework\MySQLBasedWriteModel\UI\Cli\Migrations;

use Assert\Assert;
use League\CLImate\CLImate;
use SaaSFormation\Framework\Console\UI\Command;
use SaaSFormation\Framework\Console\UI\CommandOption;
use SaaSFormation\Framework\Console\UI\InputInterface;
use SaaSFormation\Framework\MySQLBasedWriteModel\Infrastructure\WriteModel\MySQLMigrationsRepository;
use Symfony\Component\Yaml\Yaml;

readonly class MigrateCommand extends Command
{
    public function __construct(private MySQLMigrationsRepository $repository)
    {
    }

    public function cliLine(): string
    {
        return "writemodel:schema:migrate --config=config.yml";
    }

    public function description(): string
    {
        return "Migrates write model schema";
    }

    public function execute(InputInterface $input, CLImate $output): int
    {
        $output->info("Checking whether migrations table exists");
        if(!$this->repository->migrationsTableExists()) {
            $output->info("It does not exist, creating...");
            $this->repository->createMigrationsTable();
            $output->info("Migrations table created");
        } else {
            $output->info("Migrations table already exists");
        }

        $configPath = $input->options()->find('config');
        Assert::that($configPath)->isInstanceOf(CommandOption::class);
        $configPath = $configPath->value();

        /** @var array{"paths": array<int, string>} $parsedYaml */
        $parsedYaml = Yaml::parseFile($configPath . '/migrations-config.yaml');

        $output->info("Looking for migrations");
        $totalMigrationsExecuted = 0;
        foreach($parsedYaml["paths"] as $path) {
            $files = scandir($configPath . '/' . $path);

            Assert::that($files)->isArray();

            foreach ($files as $file) {
                if ($file !== '.' && $file !== '..') {
                    $migrationName = explode('.', $file)[0];
                    if(!$this->repository->isMigrationExecuted($migrationName)) {
                        $output->info("Migration $migrationName has not been executed; executing...");
                        $migration = file_get_contents($configPath . '/' . $path . '/' . $file);
                        Assert::that($migration)->string();
                        $this->repository->runMigration($migrationName, $migration);
                        $totalMigrationsExecuted++;
                        $output->info("Migration $migrationName executed");
                    }
                }
            }
        }
        $output->info("Process finished; run $totalMigrationsExecuted migrations");

        return 0;
    }
}