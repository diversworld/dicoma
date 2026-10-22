<?php
declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261020143000 extends AbstractMigration 
{
	public function getDescription():string{
		return 'Closing compressor meter reading';
	}
	
	public function up(Schema $schema):void 
	{
		if (!$this->connection->createSchemaManager()->tablesExist([
			'compressor_fill_session',
		])) {
			return;
		}
		
		$this->addSql('ALTER TABLE compressor_fill_session ADD closing_hours_tenths INT DEFAULT NULL');
	}
	
	public function down(Schema $schema):void 
	{
		$this->addSql('ALTER TABLE compressor_fill_session DROP closing_hours_tenths');
	}
 }
