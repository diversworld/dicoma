<?php

declare(strict_types=1);

namespace App\Command;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(name: 'app:test-database:check', description: 'Prüft die tatsächlich verbundene Testdatenbank ohne Schreibzugriffe')]
final class CheckTestDatabaseCommand extends Command
{
    public function __construct(
        private readonly Connection $connection,
        #[Autowire('%kernel.environment%')] private readonly string $environment,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('expected', null, InputOption::VALUE_REQUIRED, 'Exakter erwarteter Testdatenbankname');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $expected = $input->getOption('expected');
        if ($this->environment !== 'test' || !is_string($expected)
            || !preg_match('/^[a-zA-Z0-9_]+_test(?:_[a-zA-Z0-9]+)?$/D', $expected)) {
            $output->writeln('<error>Nur APP_ENV=test und ein expliziter Datenbankname mit _test-Suffix sind erlaubt.</error>');
            return Command::FAILURE;
        }
        try {
            if (!$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
                $output->writeln('<error>Diese Staging-Prüfung erfordert MySQL/MariaDB.</error>');
                return Command::FAILURE;
            }
            $actual = $this->connection->fetchOne('SELECT DATABASE()');
        } catch (Exception) {
            $output->writeln('<error>Testdatenbank nicht erreichbar; Verbindung separat prüfen.</error>');
            return Command::FAILURE;
        }
        if ($actual !== $expected) {
            $output->writeln('<error>Verbundene Datenbank entspricht nicht der ausdrücklich bestätigten Testdatenbank.</error>');
            return Command::FAILURE;
        }
        $output->writeln('<info>Testdatenbank bestätigt: ' . $expected . '</info>');
        return Command::SUCCESS;
    }
}
