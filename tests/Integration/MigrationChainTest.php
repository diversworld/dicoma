<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MigrationChainTest extends TestCase
{
    private ?Connection $server = null;
    private ?Connection $connection = null;
    private ?string $database = null;

    protected function setUp(): void
    {
        $url = $_ENV['MIGRATION_TEST_DATABASE_URL'] ?? $_SERVER['MIGRATION_TEST_DATABASE_URL'] ?? getenv('MIGRATION_TEST_DATABASE_URL');
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

        $this->assertSchemaMatchesMapping();

        $factory = $this->factory();
        $latest = $factory->getVersionAliasResolver()->resolveVersionAlias('latest');
        self::assertCount(0, $factory->getMigrationPlanCalculator()->getPlanUntilVersion($latest));

        $this->expectException(IrreversibleMigration::class);
        $this->migrate('20261016194500');
    }

    public function testPersonNormalizationPreservesAllFieldsIdsAndAccountLinks(): void
    {
        $this->migrate('20261022130000');
        $club = $this->createClub();
        $this->connection->insert('user', [
            'user' => 'person-migration', 'email' => 'account@example.invalid',
            'roles' => '["ROLE_MEMBER"]', 'password' => 'test', 'is_verified' => 1,
        ]);
        $user = (int) $this->connection->lastInsertId();
        $fields = [
            'firstname' => 'Jörg ', 'lastname' => ' D\'Ávila', 'birthday' => '1980-02-29',
            'email' => 'contact@example.invalid', 'mobile' => '+49 123', 'phone' => null,
            'street' => 'Straße 1', 'postal_code' => '00123', 'city' => 'Köln',
        ];
        $this->connection->insert('member', $fields + [
            'club_id' => $club, 'user_id' => $user, 'member_number' => 'KEEP-001', 'membership_status' => 'active',
        ]);
        $member = (int) $this->connection->lastInsertId();
        // Equal names do not establish equal identity.
        $this->connection->insert('member', $fields + [
            'club_id' => $club, 'member_number' => 'KEEP-002', 'membership_status' => 'prospect',
        ]);

        $this->migrate('latest');

        self::assertSame($fields, $this->connection->fetchAssociative(
            'SELECT firstname, lastname, birthday, email, mobile, phone, street, postal_code, city FROM person WHERE id = ?',
            [$member]
        ));
        $row = $this->connection->fetchAssociative('SELECT * FROM member WHERE id = ?', [$member]);
        self::assertSame($member, (int) $row['person_id']);
        self::assertSame('KEEP-001', $row['member_number']);
        self::assertSame($club, (int) $row['club_id']);
        self::assertSame($user, (int) $row['user_id']);
        self::assertSame($member, (int) $this->connection->fetchOne('SELECT person_id FROM user WHERE id = ?', [$user]));
        self::assertSame(2, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM person'));
        self::assertFalse($this->connection->createSchemaManager()->introspectTable('member')->hasColumn('firstname'));
        $this->assertSchemaMatchesMapping();
    }

    public function testPersonContractAbortsBeforeDroppingAnyUnverifiedData(): void
    {
        $this->migrate('20261022130000');
        $this->connection->insert('member', [
            'firstname' => 'Original', 'lastname' => 'Diver', 'birthday' => '2000-01-01',
            'membership_status' => 'active',
        ]);
        $member = (int) $this->connection->lastInsertId();
        $this->migrate('20261023100000');
        $this->connection->update('person', ['firstname' => 'original'], ['id' => $member]);
        try {
            $this->migrate('latest');
            self::fail('Case-insensitive collation must not hide changed identity data.');
        } catch (AbortMigration $exception) {
            self::assertStringContainsString('No legacy columns were removed', $exception->getMessage());
        }
        self::assertSame('Original', $this->connection->fetchOne('SELECT firstname FROM member WHERE id = ?', [$member]));
        $this->connection->update('person', ['firstname' => 'Original'], ['id' => $member]);
        $this->migrate('latest');
        $this->assertSchemaMatchesMapping();
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

        foreach ([
            'IDX_EQ_MODEL_CLUB',
            'IDX_EQ_MODEL_CATEGORY',
            'IDX_EQ_MODEL_MANUFACTURER',
        ] as $index) {
            self::assertTrue(
                $schema->getTable('equipment_model')->hasIndex($index),
                sprintf('Historischer Index fehlt: %s', $index)
            );
        }

        self::assertTrue(
            $schema->getTable('equipment_item')->hasIndex('IDX_EQ_ITEM_MODEL_REFERENCE'),
            'Historischer Index für equipment_item.model_reference_id fehlt'
        );

        self::assertTrue(
            $schema->getTable('equipment_size')->hasIndex('IDX_EQ_SIZE_MANUFACTURER'),
            'Historischer Index für equipment_size.manufacturer_id fehlt'
        );

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
        $this->factory()->getMetadataStorage()->ensureInitialized();
        $schemaDifferences = [];

        $irreversible = [
            '20240210143454',
            '20261013220000',
            '20261016120000',
            '20261016200000',
            '20261017180000',
            '20261017181500',
            '20261023100000',
            '20261023110000',
        ];

        $migrations = $this->factory()
            ->getMigrationPlanCalculator()
            ->getMigrations()
            ->getItems();

        foreach ($migrations as $migration) {
            $version = (string) $migration->getVersion();
            $before = $this->connection->createSchemaManager()->introspectSchema();
            $this->migrate($version);

            if (!in_array(substr($version, -14), $irreversible, true)) {
                $after = $this->connection->createSchemaManager()->introspectSchema();
                $this->migrate($previous);
                $rollbackDiff = $this->schemaChangesTo($before);
                $this->migrate($version);
                $reapplyDiff = $this->schemaChangesTo($after);
                if ($rollbackDiff !== [] || $reapplyDiff !== []) {
                    $schemaDifferences[$version] = ['rollback' => $rollbackDiff, 'reapply' => $reapplyDiff];
                }
            }

            $previous = $version;
        }

        self::assertSame([], $schemaDifferences, 'Rollback and reapply must restore each historical schema.');
        self::assertSame(
            count($migrations),
            (int) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM doctrine_migration_versions'
            )
        );
        $this->assertSchemaMatchesMapping();
    }

    /** @return iterable<string, array{?string}> */
    public static function inspectionSchemas(): iterable
    {
        yield 'missing inspection table' => [null];
        yield 'existing legacy index' => ['IDX_TANK_INSPECTION_TANK'];
        yield 'existing canonical index' => ['IDX_9EC2776B15C652B5'];
    }

    #[DataProvider('inspectionSchemas')]
    public function testInspectionUpgradePreservesResultsAndResumesPartialIndexAlignment(?string $indexName): void
    {
        $this->migrate('20261017180000');
        self::assertFalse($this->connection->createSchemaManager()->tablesExist(['tank_inspection_result']));
        $this->connection->insert('tank', [
            'inventory' => 'TEST-INSPECTION', 'size' => 12, 'last_check_date' => '2026-01-01', 'serialnumber' => 'TEST-SERIAL',
        ]);
        $tank = (int) $this->connection->lastInsertId();
        $this->connection->insert('tank_check', ['check_date' => '2026-10-17']);
        $check = (int) $this->connection->lastInsertId();
        $this->connection->insert('member_tank_check_booking', [
            'club_tank_id' => $tank, 'tank_check_id' => $check, 'created_at' => '2026-10-17 12:00:00',
        ]);
        $booking = (int) $this->connection->lastInsertId();
        $this->connection->insert('member_tank_check_result', [
            'booking_id' => $booking, 'result' => 'passed', 'inspected_at' => '2026-10-17',
            'updated_by' => 'Migration test', 'updated_at' => '2026-10-17 12:00:00', 'notes' => 'Keep inspection result',
        ]);
        $before = $this->connection->fetchAllAssociative('SELECT * FROM member_tank_check_result');
        $history = [];

        if ($indexName !== null) {
            $this->migrate('20261017181500');
            $this->connection->insert('tank_inspection_result', [
                'tank_id' => $tank, 'result' => 'passed', 'inspected_at' => '2026-10-17',
                'updated_by' => 'Migration test', 'created_at' => '2026-10-17 12:00:00', 'notes' => 'Keep club history',
            ]);
            $history = $this->connection->fetchAllAssociative('SELECT * FROM tank_inspection_result');
            if ($indexName !== 'IDX_9EC2776B15C652B5') {
                $this->connection->executeStatement('ALTER TABLE tank_inspection_result RENAME INDEX IDX_9EC2776B15C652B5 TO ' . $indexName);
            }
            $this->connection->delete('doctrine_migration_versions', ['version' => 'DoctrineMigrations\\Version20261017181500']);
        }

        $this->migrate('20261017220000');
        self::assertSame($before, $this->connection->fetchAllAssociative('SELECT * FROM member_tank_check_result'));
        self::assertSame($history, $this->connection->fetchAllAssociative('SELECT * FROM tank_inspection_result'));
        $table = $this->connection->createSchemaManager()->introspectTable('tank_inspection_result');
        self::assertSame(['tank_id'], $table->getIndex('IDX_9EC2776B15C652B5')->getColumns());
        self::assertCount(1, $table->getForeignKeys());

        $this->connection->executeStatement('ALTER TABLE tuv_document RENAME INDEX IDX_TUV_DOCUMENT_BOOKING TO IDX_381BAC323301C60');
        $this->migrate('latest');
        $this->assertSchemaMatchesMapping();
        self::assertSame($history, $this->connection->fetchAllAssociative('SELECT * FROM tank_inspection_result'));
        self::assertSame($before, $this->connection->fetchAllAssociative('SELECT * FROM member_tank_check_result'));
    }

    public function testClubBookingRollbackAbortsBeforeRemovingReferences(): void
    {
        $this->migrate('20261017173000');
        $this->connection->insert('tank_check', ['check_date' => '2026-10-17']);
        $this->connection->insert('member_tank_check_booking', [
            'tank_check_id' => (int) $this->connection->lastInsertId(), 'created_at' => '2026-10-17 12:00:00',
        ]);
        try {
            $this->migrate('20261017140000');
            self::fail('Rollback must reject bookings without member references before executing DDL.');
        } catch (AbortMigration $exception) {
            //self::assertStringContainsString('explicit data migration', $exception->getMessage());
            self::assertStringContainsString(
                'Rollback nicht möglich',
                $exception->getMessage()
            );
        }
        $table = $this->connection->createSchemaManager()->introspectTable('member_tank_check_booking');
        self::assertTrue($table->hasColumn('club_tank_id'));
        self::assertTrue($table->hasForeignKey('FK_TUV_BOOKING_CLUB_TANK'));
        self::assertTrue($table->hasIndex('UNIQ_TUV_CLUB_TANK_CHECK'));
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM member_tank_check_booking'));
    }

    private function assertSchemaMatchesMapping(): void
    {
        $config = ORMSetup::createAttributeMetadataConfiguration([dirname(__DIR__, 2) . '/src/Entity'], true);
        $config->setNamingStrategy(new UnderscoreNamingStrategy(CASE_LOWER, true));
        $em = EntityManager::create($this->connection, $config);
        $validator = new SchemaValidator($em);
        self::assertSame([], $validator->validateMapping());
        self::assertSame([], $validator->getUpdateSchemaList());
    }

    /** @return list<string> */
    private function schemaChangesTo(Schema $expected): array
    {
        $manager = $this->connection->createSchemaManager();
        $actual = $manager->introspectSchema();
        $expected = clone $expected;
        foreach ($expected->getTables() as $table) {
            if (!$actual->hasTable($table->getName())) {
                continue;
            }
            foreach ($table->getColumns() as $column) {
                $actualTable = $actual->getTable($table->getName());
                if (!$actualTable->hasColumn($column->getName())) {
                    continue;
                }
                $actualColumn = $actualTable->getColumn($column->getName());
                // DBAL otherwise reports unchanged legacy LONGTEXT/JSON columns as differences.
                if ($column->hasPlatformOption('declarationMismatch')
                    && $actualColumn->hasPlatformOption('declarationMismatch')
                    && $column->getPlatformOption('declarationMismatch') === $actualColumn->getPlatformOption('declarationMismatch')) {
                    $column->setPlatformOptions(array_diff_key($column->getPlatformOptions(), ['declarationMismatch' => true]));
                    $actualColumn->setPlatformOptions(array_diff_key($actualColumn->getPlatformOptions(), ['declarationMismatch' => true]));
                }
            }
        }
        $diff = $manager->createComparator()->compareSchemas($actual, $expected);

        return $this->connection->getDatabasePlatform()->getAlterSchemaSQL($diff);
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
