<?php
declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20261020090000 extends AbstractMigration {
    public function getDescription(): string { return 'Leihgebühr für Vereinsflaschen'; }
    public function up(Schema $schema): void { $this->addSql("ALTER TABLE tank ADD rental_fee NUMERIC(10, 2) DEFAULT '0.00' NOT NULL"); }
    public function down(Schema $schema): void { $this->addSql('ALTER TABLE tank DROP rental_fee'); }
}
