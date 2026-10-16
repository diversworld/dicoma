<?php

declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20261016160000 extends AbstractMigration
{
    public function getDescription(): string { return 'Snapshot of selected TÜV service and price on member booking'; }
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE member_tank_check_booking ADD article_title_snapshot VARCHAR(255) DEFAULT NULL, ADD price_snapshot NUMERIC(10, 2) DEFAULT NULL, ADD price_basis VARCHAR(10) DEFAULT NULL');
    }
    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE member_tank_check_booking DROP article_title_snapshot, DROP price_snapshot, DROP price_basis');
    }
}
