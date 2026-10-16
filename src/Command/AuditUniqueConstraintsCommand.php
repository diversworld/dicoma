<?php

declare(strict_types=1);

namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:audit-unique-constraints', description: 'Read-only audit of six DiCoMa uniqueness rules')]
final class AuditUniqueConstraintsCommand extends Command
{
    private const RULES = [
        'course_training_unit' => ['course_id', 'training_unit_type_id'],
        'qualification_training_unit' => ['qualification_id', 'training_unit_type_id'],
        'schedule' => ['courses_id', 'training_unit_type_id', 'training_unit_sequence'],
        'schedule_template_entry' => ['template_id', 'training_unit_type_id', 'sequence'],
        'training_unit_result' => ['course_participant_id', 'schedule_id'],
        'training_unit_type' => ['club_id', 'code'],
    ];

    public function __construct(private readonly Connection $connection)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $schema = $this->connection->createSchemaManager();
        $problems = 0;
        foreach (self::RULES as $table => $columns) {
            if (!$schema->tablesExist([$table])) {
                $output->writeln("[PRUEFEN] Tabelle fehlt: $table");
                ++$problems;
                continue;
            }
            $existing = array_map(static fn ($col) => $col->getName(), $schema->listTableColumns($table));
            $missing = array_diff($columns, $existing);
            if ($missing !== []) {
                $output->writeln("[PRUEFEN] $table: Spalten fehlen: " . implode(', ', $missing));
                ++$problems;
                continue;
            }
            $unique = false;
            foreach ($schema->listTableIndexes($table) as $index) {
                if ($index->isUnique() && $index->getColumns() === $columns) {
                    $unique = true;
                    break;
                }
            }
            // Identifiers are constants above, never user input.
            $quoted = array_map(fn (string $column): string => $this->connection->quoteIdentifier($column), $columns);
            $sql = 'SELECT COUNT(*) FROM (SELECT 1 FROM ' . $this->connection->quoteIdentifier($table)
                . ' GROUP BY ' . implode(', ', $quoted) . ' HAVING COUNT(*) > 1) AS duplicate_groups';
            $duplicates = (int) $this->connection->fetchOne($sql);
            $output->writeln(sprintf('[%s] %s: UNIQUE=%s; doppelte Gruppen=%d; Spalten=%s',
                $unique && $duplicates === 0 ? 'OK' : 'PRUEFEN',
                $table, $unique ? 'ja' : 'nein', $duplicates, implode(', ', $columns)
            ));
            if (!$unique || $duplicates > 0) {
                ++$problems;
            }
        }
        $output->writeln($problems === 0 ? 'Alle Regeln vorhanden. Keine Daten geaendert.' : "$problems Regel(n) erfordern Pruefung. Keine Daten geaendert.");
        return $problems === 0 ? Command::SUCCESS : Command::FAILURE;
    }
}
