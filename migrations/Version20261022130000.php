<?php
declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261022130000 extends AbstractMigration 
{
	public function getDescription():string{
		return 'Rename Indexes';
	}
	
	public function up(Schema $schema):void 
	{
		$this->addSql("ALTER TABLE courses ADD course_fee NUMERIC(10, 2) DEFAULT '0.00' NOT NULL;");
	}
	
	public function down(Schema $schema):void 
	{
		$this->addSql('ALTER TABLE courses DROP course_fee;');
	}
 }
