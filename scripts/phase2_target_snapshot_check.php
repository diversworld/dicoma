<?php

declare(strict_types=1);

use Doctrine\DBAL\DriverManager;
use Doctrine\Migrations\Configuration\Connection\ExistingConnection;
use Doctrine\Migrations\Configuration\Migration\ConfigurationArray;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\MigratorConfiguration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\UnderscoreNamingStrategy;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaValidator;

require dirname(__DIR__) . '/vendor/autoload.php';

$url = getenv('DATABASE_URL');
if (getenv('DICOMA_PHASE2_TARGET_SNAPSHOT_CONFIRMED') !== '1' || $url === false) {
    fwrite(STDERR, "BLOCKIERT: Nur mit ausdruecklicher Snapshot-Freigabe und isolierter DATABASE_URL.\n");
    exit(2);
}

$connection = DriverManager::getConnection(['url' => $url]);
$params = $connection->getParams();
$database = $connection->fetchOne('SELECT DATABASE()');
if (($params['host'] ?? null) !== 'db_test' || !is_string($database)
    || !preg_match('/^dicoma_snapshot_test_[a-f0-9]{16}$/D', $database)) {
    fwrite(STDERR, "BLOCKIERT: Ausschliesslich neue Snapshot-Datenbanken auf db_test erlaubt.\n");
    exit(2);
}

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

/** @return list<string> */
function fingerprints(\Doctrine\DBAL\Connection $connection, string $table, array $columns): array
{
    $names = array_map($connection->quoteIdentifier(...), $columns);
    $sql = 'SELECT ' . implode(', ', $names) . ' FROM ' . $connection->quoteIdentifier($table);
    $hashes = [];
    foreach ($connection->iterateAssociative($sql) as $row) {
        $hashes[] = hash('sha256', serialize($row));
    }
    sort($hashes);
    return $hashes;
}

$personalFields = ['firstname', 'lastname', 'birthday', 'email', 'mobile', 'phone', 'street', 'postal_code', 'city'];
$schema = $connection->createSchemaManager()->introspectSchema();
check($schema->hasTable('member'), 'Expected an existing membership schema.');
$extractPeople = !$schema->hasTable('person');
$before = [];
foreach ($schema->getTables() as $table) {
    if ($table->getName() === 'doctrine_migration_versions') {
        continue;
    }
    $columns = array_keys($table->getColumns());
    if ($extractPeople && $table->getName() === 'member') {
        $columns = array_values(array_diff($columns, $personalFields));
    }
    $before[$table->getName()] = [
        'columns' => $columns,
        'rows' => fingerprints($connection, $table->getName(), $columns),
    ];
}
$members = $connection->fetchAllAssociative('SELECT * FROM member ORDER BY id');
$sourceVersions = $connection->fetchFirstColumn('SELECT version FROM doctrine_migration_versions ORDER BY version');
check($sourceVersions !== [], 'Source migration history is empty.');
echo 'Source migration: ' . end($sourceVersions) . "\n";
echo 'Snapshot baseline: ' . count($before) . ' application tables, ' . count($members) . " members.\n";

$factory = DependencyFactory::fromConnection(
    new ConfigurationArray(['migrations_paths' => ['DoctrineMigrations' => dirname(__DIR__) . '/migrations']]),
    new ExistingConnection($connection),
);
$factory->getMetadataStorage()->ensureInitialized();
$latest = $factory->getVersionAliasResolver()->resolveVersionAlias('latest');
$plan = $factory->getMigrationPlanCalculator()->getPlanUntilVersion($latest);
$migrationCount = count($plan->getItems());
$factory->getMigrator()->migrate($plan, new MigratorConfiguration());

foreach ($before as $table => $state) {
    check($state['rows'] === fingerprints($connection, $table, $state['columns']), 'Original rows changed in table ' . $table);
}
foreach ($extractPeople ? $members : [] as $member) {
    $person = $connection->fetchAssociative('SELECT * FROM person WHERE id = ?', [$member['id']]);
    check(is_array($person), 'Extracted person is missing.');
    foreach ($personalFields as $field) {
        check($member[$field] === $person[$field], 'Extracted person field differs: ' . $field);
    }
    $normalized = $connection->fetchAssociative('SELECT * FROM member WHERE id = ?', [$member['id']]);
    check(is_array($normalized) && $normalized['person_id'] === $member['id'], 'Member/person identity changed.');
    if ($member['user_id'] !== null) {
        check(
            $connection->fetchOne('SELECT person_id FROM `user` WHERE id = ?', [$member['user_id']]) === $member['id'],
            'Account/person assignment differs.',
        );
    }
}
if ($extractPeople) {
    check((int) $connection->fetchOne('SELECT COUNT(*) FROM person') === count($members), 'Unexpected number of extracted persons.');
}

$config = ORMSetup::createAttributeMetadataConfiguration([dirname(__DIR__) . '/src/Entity'], true);
$config->setNamingStrategy(new UnderscoreNamingStrategy(CASE_LOWER, true));
$em = EntityManager::create($connection, $config);
$validator = new SchemaValidator($em);
check($validator->validateMapping() === [], 'Mapping validation failed.');
$changes = $validator->getUpdateSchemaList();
if ($changes !== []) {
    fwrite(STDERR, "Remaining schema differences:\n" . implode("\n", $changes) . "\n");
}
check($changes === [], 'Restored and migrated target schema differs from current mapping.');
check(count($factory->getMigrationPlanCalculator()->getPlanUntilVersion($latest)) === 0, 'Migrations remain pending.');

foreach ([
    ['member_document', 'storage_name', 'member_documents', 'file_size', null],
    ['tuv_document', 'stored_name', 'tuv_documents', 'size', 'sha256'],
] as [$table, $nameColumn, $directory, $sizeColumn, $checksumColumn]) {
    $rows = $connection->fetchAllAssociative('SELECT * FROM ' . $connection->quoteIdentifier($table));
    foreach ($rows as $row) {
        $name = $row[$nameColumn];
        check(is_string($name) && preg_match('/^[a-f0-9]{40}\.(pdf|jpg|png)$/D', $name) === 1, 'Invalid document storage name.');
        $path = dirname(__DIR__) . '/var/private/' . $directory . '/' . $name;
        check(is_file($path), 'Referenced private document is missing.');
        check(filesize($path) === (int) $row[$sizeColumn], 'Restored document size differs.');
        if ($checksumColumn !== null) {
            check(hash_file('sha256', $path) === $row[$checksumColumn], 'Restored document checksum differs.');
        }
    }
    echo 'Restored document references checked: ' . $table . ' = ' . count($rows) . "\n";
}

echo 'PASS: ' . $migrationCount . ' target migrations; original rows in ' . count($before)
    . " tables unchanged; all person fields and account links preserved; schema synchronized.\n";
$connection->close();
