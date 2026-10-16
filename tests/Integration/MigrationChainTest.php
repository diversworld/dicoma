<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\Migrations\Configuration\Connection\ExistingConnection;
use Doctrine\Migrations\Configuration\Migration\ConfigurationArray;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\Exception\AbortMigration;
use Doctrine\Migrations\Exception\IrreversibleMigration;
use Doctrine\Migrations\MigratorConfiguration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\UnderscoreNamingStrategy;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaValidator;
use PHPUnit\Framework\TestCase;

final class MigrationChainTest extends TestCase
{
    private ?Connection $server = null;
    private ?Connection $connection = null;
    private ?string $database = null;

    protected function setUp(): void
    {
        $url = getenv('MIGRATION_TEST_DATABASE_URL');
        if ($url === false || $url === '') {
            self::markTestSkipped('Set MIGRATION_TEST_DATABASE_URL to a MySQL/MariaDB server with CREATE DATABASE permission.');
        }

        $this->server = DriverManager::getConnection(['url' => $url]);
        self::assertInstanceOf(AbstractMySQLPlatform::class, $this->server->getDatabasePlatform());
        $database = 'dicoma_migration_test_' . bin2hex(random_bytes(8));
        $this->server->executeStatement('CREATE DATABASE `' . $database . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->database = $database;
        $params = $this->server->getParams();
        unset($params['url']);
        $params['dbname'] = $database;
        $this->connection = DriverManager::getConnection($params);
        self::assertSame($database, $this->connection->fetchOne('SELECT DATABASE()'));
    }

    protected function tearDown(): void
    {
        $this->connection?->close();
        if ($this->database !== null) {
            $this->server?->executeStatement('DROP DATABASE `' . $this->database . '`');
        }
        $this->server?->close();
        parent::tearDown();
    }

    public function testFreshInstallationMatchesMappingAndHasNoPendingMigrations(): void
    {
        $this->migrate('latest');

        $config = ORMSetup::createAttributeMetadataConfiguration([dirname(__DIR__, 2) . '/src/Entity'], true);
        $config->setNamingStrategy(new UnderscoreNamingStrategy(CASE_LOWER, true));
        $em = EntityManager::create($this->connection, $config);
        $validator = new SchemaValidator($em);
        self::assertSame([], $validator->validateMapping());
        self::assertSame([], $validator->getUpdateSchemaList());

        $factory = $this->factory();
        $latest = $factory->getVersionAliasResolver()->resolveVersionAlias('latest');
        self::assertCount(0, $factory->getMigrationPlanCalculator()->getPlanUntilVersion($latest));

        $this->expectException(IrreversibleMigration::class);
        $this->migrate('20261016194500');
    }

    public function testUpgradeFromReportedFailurePreservesInventoryAndIndexes(): void
    {
        $this->migrate('20261014100000');
        $club = $this->createClub();
        $this->connection->insert('equipment_category', ['club_id' => $club, 'code' => 'suit', 'name' => 'Suit', 'active' => 1]);
        $category = (int) $this->connection->lastInsertId();
        $this->connection->insert('equipment_manufacturer', ['club_id' => $club, 'name' => 'Manufacturer', 'active' => 1]);
        $manufacturer = (int) $this->connection->lastInsertId();
        $this->connection->insert('equipment_size', [
            'club_id' => $club, 'category_id' => $category, 'manufacturer_id' => $manufacturer, 'name' => 'M', 'active' => 1,
        ]);
        $size = (int) $this->connection->lastInsertId();
        $this->connection->insert('equipment_item', [
            'club_id' => $club, 'category_id' => $category, 'manufacturer_id' => $manufacturer, 'size_id' => $size,
            'inventory_number' => 'TEST-001', 'name' => 'Existing suit', 'stock_type' => 'single',
            'total_quantity' => 1, 'condition_status' => 'ready', 'lendable' => 1, 'attribute_values' => '{}',
        ]);
        $before = $this->connection->fetchAllAssociative('SELECT * FROM equipment_item');

        $this->migrate('20261014130000');
        $schema = $this->connection->createSchemaManager()->introspectSchema();
        foreach (['IDX_3ECC533D61190A32', 'IDX_3ECC533D12469DE2', 'IDX_3ECC533DA23B42D'] as $index) {
            self::assertTrue($schema->getTable('equipment_model')->hasIndex($index));
        }
        self::assertTrue($schema->getTable('equipment_item')->hasIndex('IDX_259FF418BF9772C3'));
        self::assertTrue($schema->getTable('equipment_size')->hasIndex('IDX_CD44F56CA23B42D'));

        $this->migrate('20261014100000');
        self::assertSame($before, $this->connection->fetchAllAssociative('SELECT * FROM equipment_item'));
        $this->migrate('latest');
        $after = $this->connection->fetchAllAssociative('SELECT * FROM equipment_item');
        self::assertNull($after[0]['model_reference_id']);
        unset($after[0]['model_reference_id']);
        self::assertSame($before, $after);
        self::assertSame((string) $manufacturer, (string) $this->connection->fetchOne('SELECT manufacturer_id FROM equipment_size WHERE id = ?', [$size]));
    }

    public function testDuplicateConstraintsAbortBeforeDdlAndCanBeRetried(): void
    {
        $this->migrate('20261016194500');
        $club = $this->createClub();
        foreach (['First', 'Second'] as $name) {
            $this->connection->insert('training_unit_type', ['club_id' => $club, 'code' => 'duplicate', 'name' => $name]);
        }
        $second = (int) $this->connection->lastInsertId();

        try {
            $this->migrate('latest');
            self::fail('Duplicate training units must not be silently deleted or merged.');
        } catch (AbortMigration $exception) {
            self::assertStringContainsString('uniq_training_unit_type_club_code', $exception->getMessage());
            self::assertStringContainsString('duplicate', $exception->getMessage());
        }
        self::assertSame(2, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM training_unit_type'));
        $schema = $this->connection->createSchemaManager()->introspectSchema();
        self::assertFalse($schema->getTable('tank')->hasColumn('condition_status'));
        self::assertFalse($schema->getTable('qualification_training_unit')->hasIndex('uniq_qualification_training_unit'));

        $this->connection->update('training_unit_type', ['code' => 'resolved'], ['id' => $second]);
        foreach (['Global first', 'Global second'] as $name) {
            $this->connection->insert('training_unit_type', ['club_id' => null, 'code' => 'global', 'name' => $name]);
        }
        $this->connection->executeStatement('CREATE UNIQUE INDEX uniq_qualification_training_unit ON qualification_training_unit (qualification_id, training_unit_type_id)');
        $this->connection->executeStatement("ALTER TABLE equipment_reservation ADD issued_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'");
        $this->migrate('latest');
        self::assertSame(4, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM training_unit_type'));
        self::assertTrue($this->connection->createSchemaManager()->introspectTable('training_unit_type')->hasIndex('uniq_training_unit_type_club_code'));
    }

    public function testHistoricalReversibleMigrationsCanBeRolledBackAndReapplied(): void
    {
        $previous = '0';
        $irreversible = ['20261013220000', '20261016120000', '20261016200000'];
        $migrations = $this->factory()->getMigrationPlanCalculator()->getMigrations()->getItems();
        foreach ($migrations as $migration) {
            $version = (string) $migration->getVersion();
            $this->migrate($version);
            if (!in_array(substr($version, -14), $irreversible, true)) {
                $this->migrate($previous);
                $this->migrate($version);
            }
            $previous = $version;
        }
        self::assertSame(count($migrations), (int) $this->connection->fetchOne('SELECT COUNT(*) FROM doctrine_migration_versions'));
    }

    private function createClub(): int
    {
        $this->connection->insert('club', [
            'name' => 'Migration test', 'country' => 'DE', 'active' => 1,
            'created_at' => '2026-10-16 12:00:00', 'updated_at' => '2026-10-16 12:00:00',
        ]);

        return (int) $this->connection->lastInsertId();
    }

    private function factory(): DependencyFactory
    {
        return DependencyFactory::fromConnection(
            new ConfigurationArray(['migrations_paths' => ['DoctrineMigrations' => dirname(__DIR__, 2) . '/migrations']]),
            new ExistingConnection($this->connection)
        );
    }

    private function migrate(string $target): void
    {
        $factory = $this->factory();
        $factory->getMetadataStorage()->ensureInitialized();
        if (ctype_digit($target) && $target !== '0') {
            $target = 'DoctrineMigrations\\Version' . $target;
        }
        $version = $factory->getVersionAliasResolver()->resolveVersionAlias($target);
        $plan = $factory->getMigrationPlanCalculator()->getPlanUntilVersion($version);
        $factory->getMigrator()->migrate($plan, new MigratorConfiguration());
    }
}
