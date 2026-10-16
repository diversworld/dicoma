<?php

declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20261016150000 extends AbstractMigration
{
    public function getDescription(): string { return 'TÜV-Veröffentlichung und Anmeldung privater Flaschen'; }
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tank_check ADD is_published TINYINT(1) DEFAULT 0 NOT NULL');
        $this->addSql("CREATE TABLE member_tank_check_booking (id INT AUTO_INCREMENT NOT NULL, member_id INT NOT NULL, member_tank_id INT NOT NULL, tank_check_id INT NOT NULL, status VARCHAR(20) DEFAULT 'requested' NOT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX IDX_BOOK_MEMBER (member_id), INDEX IDX_BOOK_TANK (member_tank_id), INDEX IDX_BOOK_CHECK (tank_check_id), UNIQUE INDEX uniq_member_tank_check (member_tank_id, tank_check_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('ALTER TABLE member_tank_check_booking ADD CONSTRAINT FK_BOOK_MEMBER FOREIGN KEY (member_id) REFERENCES member (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE member_tank_check_booking ADD CONSTRAINT FK_BOOK_TANK FOREIGN KEY (member_tank_id) REFERENCES member_tank (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE member_tank_check_booking ADD CONSTRAINT FK_BOOK_CHECK FOREIGN KEY (tank_check_id) REFERENCES tank_check (id) ON DELETE RESTRICT');
    }
    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE member_tank_check_booking');
        $this->addSql('ALTER TABLE tank_check DROP is_published');
    }
}
