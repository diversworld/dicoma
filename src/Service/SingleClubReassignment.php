<?php

declare(strict_types=1);

namespace App\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;

final class SingleClubReassignment
{
    public function __construct(private readonly Connection $connection) {}

    /**
     * @return list<string>
     */
    public function blockers(int $targetId, ?int $allowedTournamentId = null): array
    {
        $schema = $this->connection->createSchemaManager();
        $blockers = [];
        foreach ($schema->listTableNames() as $table) {
            $columns = $schema->listTableColumns($table);
            $clubColumns = [];
            foreach ($columns as $column) {
                if (strtolower($column->getName()) === 'club_id') {
                    $clubColumns[$column->getName()] = true;
                }
            }
            foreach ($schema->listTableForeignKeys($table) as $foreignKey) {
                if (strtolower($foreignKey->getForeignTableName()) === 'club'
                    && $foreignKey->getForeignColumns() === ['id']) {
                    foreach ($foreignKey->getLocalColumns() as $column) {
                        $clubColumns[$column] = true;
                    }
                }
            }
            foreach (array_keys($clubColumns) as $column) {
                $quotedTable = $this->connection->quoteIdentifier($table);
                $quotedColumn = $this->connection->quoteIdentifier($column);
                $sql = "SELECT COUNT(*) FROM $quotedTable WHERE $quotedColumn IS NOT NULL AND $quotedColumn <> :target";
                $parameters = ['target' => $targetId];
                if ($table === 'tournament' && $column === 'club_id' && $allowedTournamentId !== null) {
                    $sql .= ' AND id <> :tournament';
                    $parameters['tournament'] = $allowedTournamentId;
                }
                $count = (int) $this->connection->fetchOne($sql, $parameters);
                if ($count > 0) {
                    $blockers[] = sprintf('%s.%s: %d andere Vereinszuordnungen', $table, $column, $count);
                }
                if ($column === 'club_id' && in_array($table, ['member', 'courses', 'tank'], true)) {
                    $unassigned = (int) $this->connection->fetchOne("SELECT COUNT(*) FROM $quotedTable WHERE $quotedColumn IS NULL");
                    if ($unassigned > 0) {
                        $blockers[] = sprintf('%s.%s: %d nicht zugeordnete Datensätze', $table, $column, $unassigned);
                    }
                }
            }
        }

        return $blockers;
    }

    /**
     * Moves only the explicitly reviewed tournament; other clubs and their data remain untouched.
     *
     * @return list<string>
     */
    public function run(int $targetId, int $sourceId, int $tournamentId, bool $apply): array
    {
        if ($targetId < 1 || $sourceId < 1 || $tournamentId < 1 || $targetId === $sourceId) {
            throw new \DomainException('Zielverein, Quellverein und Turnier müssen gültig und eindeutig sein.');
        }

        return $this->connection->transactional(function () use ($targetId, $sourceId, $tournamentId, $apply): array {
            $platform = $this->connection->getDatabasePlatform();
            $lock = $apply && ($platform instanceof AbstractMySQLPlatform || $platform instanceof PostgreSQLPlatform) ? ' FOR UPDATE' : '';
            $target = $this->connection->fetchAssociative('SELECT id, active FROM club WHERE id = ?'.$lock, [$targetId]);
            $source = $this->connection->fetchAssociative('SELECT id FROM club WHERE id = ?'.$lock, [$sourceId]);
            $tournament = $this->connection->fetchAssociative('SELECT id, club_id, version FROM tournament WHERE id = ?'.$lock, [$tournamentId]);
            if ($target === false || !(bool) $target['active'] || $source === false) {
                throw new \DomainException('Aktiver Zielverein oder Quellverein nicht gefunden.');
            }
            if ($tournament === false || (int) $tournament['club_id'] !== $sourceId) {
                throw new \DomainException('Das angegebene Turnier gehört nicht zum Quellverein.');
            }

            $blockers = $this->blockers($targetId, $tournamentId);
            if ($blockers !== []) {
                return $blockers;
            }
            if ($apply) {
                $updated = $this->connection->executeStatement(
                    'UPDATE tournament SET club_id = ?, version = version + 1 WHERE id = ? AND club_id = ? AND version = ?',
                    [$targetId, $tournamentId, $sourceId, $tournament['version']],
                );
                if ($updated !== 1) {
                    throw new \RuntimeException('Das Turnier wurde gleichzeitig geändert; keine Zuordnung übernommen.');
                }
            }
            return [];
        });
    }
}
