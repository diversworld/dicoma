<?php

declare(strict_types=1);

namespace App\Service;

use Doctrine\DBAL\Connection;
use Doctrine\Migrations\Configuration\Connection\ExistingConnection;
use Doctrine\Migrations\Configuration\Migration\ConfigurationArray;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\MigratorConfiguration;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class InstallationMigrator
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
        private readonly Connection $connection,
        private readonly LoggerInterface $logger,
    ) {}

    public function migrate(): void
    {
        try {
            $this->connection->executeQuery('SELECT 1');
        } catch (\Throwable $exception) {
            $this->logger->error('Installer database connection failed.', [
                'exception_class' => $exception::class,
                'sqlstate' => $exception->getCode(),
            ]);
            throw new \RuntimeException('Die Verbindung zur angegebenen Datenbank ist fehlgeschlagen. Prüfen Sie Host, Datenbankname, Benutzer und Passwort.');
        }

        try {
            $factory = DependencyFactory::fromConnection(
                new ConfigurationArray([
                    'migrations_paths' => ['DoctrineMigrations' => $this->projectDir.'/migrations'],
                ]),
                new ExistingConnection($this->connection),
                $this->logger,
            );
            $factory->getMetadataStorage()->ensureInitialized();
            $latest = $factory->getVersionAliasResolver()->resolveVersionAlias('latest');
            $plan = $factory->getMigrationPlanCalculator()->getPlanUntilVersion($latest);
            $factory->getMigrator()->migrate($plan, new MigratorConfiguration());
        } catch (\Throwable $exception) {
            $this->logger->critical('Installer database migration failed.', [
                'exception' => $exception,
            ]);
            throw new \RuntimeException(
                'Die Datenbankmigration ist fehlgeschlagen: '.$exception->getMessage(),
                0,
                $exception,
            );
        }
    }
}
