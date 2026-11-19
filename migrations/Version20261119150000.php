<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261119150000 extends AbstractMigration
{
    public function getDescription(): string { return 'Add bar shift checklists, handovers and stock entries'; }
    public function isTransactional(): bool { return false; }

    public function up(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform, 'Only MySQL/MariaDB is supported.');
        $this->addSql('CREATE TABLE bar_shift_checklist (id INT AUTO_INCREMENT NOT NULL, shift_id INT NOT NULL, completed_by_id INT DEFAULT NULL, items JSON NOT NULL, handover LONGTEXT DEFAULT NULL, completed_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX uniq_bar_shift_checklist (shift_id), INDEX IDX_bar_shift_checklist_completed_by (completed_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE bar_shift_checklist ADD CONSTRAINT FK_bar_shift_checklist_shift FOREIGN KEY (shift_id) REFERENCES bar_shift (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE bar_shift_checklist ADD CONSTRAINT FK_bar_shift_checklist_member FOREIGN KEY (completed_by_id) REFERENCES member (id) ON DELETE SET NULL');
        $this->addSql('CREATE TABLE bar_stock_entry (id INT AUTO_INCREMENT NOT NULL, shift_id INT NOT NULL, reported_by_id INT NOT NULL, item VARCHAR(180) NOT NULL, opening INT NOT NULL, consumed INT NOT NULL, closing INT NOT NULL, notes LONGTEXT DEFAULT NULL, reported_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_bar_stock_entry_shift (shift_id), INDEX IDX_bar_stock_entry_reporter (reported_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE bar_stock_entry ADD CONSTRAINT FK_bar_stock_entry_shift FOREIGN KEY (shift_id) REFERENCES bar_shift (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE bar_stock_entry ADD CONSTRAINT FK_bar_stock_entry_member FOREIGN KEY (reported_by_id) REFERENCES member (id) ON DELETE RESTRICT');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform, 'Only MySQL/MariaDB is supported.');
        $this->addSql('DROP TABLE bar_stock_entry');
        $this->addSql('DROP TABLE bar_shift_checklist');
    }
}
