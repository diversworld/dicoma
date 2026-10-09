<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261007075848 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
		$this->addSql(
			'CREATE UNIQUE INDEX uniq_member_club_number
			 ON member (club_id, member_number)'
		);

		$this->addSql(
			'CREATE UNIQUE INDEX uniq_sport_club_name
			 ON sport (club_id, name)'
		);

		$this->addSql(
			'CREATE UNIQUE INDEX uniq_member_sport
			 ON member_sport (member_id, sport_id)'
		);

    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
		$this->addSql(
			'DROP INDEX uniq_member_club_number ON member'
		);

		$this->addSql(
			'DROP INDEX uniq_sport_club_name ON sport'
		);

		$this->addSql(
			'DROP INDEX uniq_member_sport ON member_sport'
		);
    }
	
}
