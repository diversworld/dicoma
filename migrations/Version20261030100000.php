<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261030100000 extends AbstractMigration
{
    public function getDescription(): string { return 'Add time-limited division role assignments'; }
    public function isTransactional(): bool { return false; }

    public function up(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform, 'Only MySQL/MariaDB is supported.');
        $this->addSql("CREATE TABLE division_role_assignment (id INT AUTO_INCREMENT NOT NULL, sport_id INT NOT NULL, member_id INT NOT NULL, role VARCHAR(30) NOT NULL, starts_at DATE NOT NULL COMMENT '(DC2Type:date_immutable)', ends_at DATE DEFAULT NULL COMMENT '(DC2Type:date_immutable)', INDEX IDX_16BEFF72AC78BCF8 (sport_id), INDEX IDX_16BEFF727597D3FE (member_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('ALTER TABLE division_role_assignment ADD CONSTRAINT fk_division_role_sport FOREIGN KEY (sport_id) REFERENCES sport (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE division_role_assignment ADD CONSTRAINT fk_division_role_member FOREIGN KEY (member_id) REFERENCES member (id) ON DELETE RESTRICT');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Spartenfunktionen und ihre Berechtigungszeiträume müssen erhalten bleiben.');
    }
}
