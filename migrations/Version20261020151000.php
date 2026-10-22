<?php
declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20261020151000 extends AbstractMigration 
{
	public function getDescription():string
	{
		return 'Cylinder volume per compressor fill';
	}
	public function up(Schema $schema): void
	{
		if (!$this->connection->createSchemaManager()->tablesExist(['compressor_fill_cylinder'])) {
			return;
		}

		if ($this->connection->createSchemaManager()
			->introspectTable('compressor_fill_cylinder')
			->hasColumn('volume_liters')) {
			return;
		}

		$this->addSql(
			'ALTER TABLE compressor_fill_cylinder ADD volume_liters NUMERIC(5, 1) DEFAULT NULL'
		);
	}
	
	public function down(Schema $schema):void 
	{
		$this->addSql('ALTER TABLE compressor_fill_cylinder DROP volume_liters');
	}
}
