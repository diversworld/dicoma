<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261016180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Prepare volume-dependent multi-article TÜV bookings without changing existing bookings';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE tank_check_article ADD selection_mode VARCHAR(16) NOT NULL DEFAULT 'optional', ADD volume_rule VARCHAR(12) NOT NULL DEFAULT 'any'");
        $this->addSql("CREATE TABLE member_tank_check_booking_item (id INT AUTO_INCREMENT NOT NULL, booking_id INT NOT NULL, article_id INT NOT NULL, title_snapshot VARCHAR(255) NOT NULL, net_snapshot NUMERIC(10, 2) NOT NULL, gross_snapshot NUMERIC(10, 2) NOT NULL, quantity INT NOT NULL, INDEX IDX_BOOKING_ITEM_BOOKING (booking_id), INDEX IDX_BOOKING_ITEM_ARTICLE (article_id), UNIQUE INDEX uniq_booking_article (booking_id, article_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('ALTER TABLE member_tank_check_booking_item ADD CONSTRAINT FK_BOOKING_ITEM_BOOKING FOREIGN KEY (booking_id) REFERENCES member_tank_check_booking (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE member_tank_check_booking_item ADD CONSTRAINT FK_BOOKING_ITEM_ARTICLE FOREIGN KEY (article_id) REFERENCES tank_check_article (id) ON DELETE RESTRICT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE member_tank_check_booking_item');
        $this->addSql('ALTER TABLE tank_check_article DROP selection_mode, DROP volume_rule');
    }
}
