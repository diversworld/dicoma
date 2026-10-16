<?php

declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20261016170000 extends AbstractMigration
{
    public function getDescription(): string { return 'Optional registration deadline for published tank checks'; }
    public function up(Schema $schema): void { $this->addSql('ALTER TABLE tank_check ADD registration_deadline DATE DEFAULT NULL'); }
    public function down(Schema $schema): void { $this->addSql('ALTER TABLE tank_check DROP registration_deadline'); }
}
