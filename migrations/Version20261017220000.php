<?php
declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20261017220000 extends AbstractMigration
{
    public function getDescription(): string { return 'TÜV-Benachrichtigungen pro Vorlage aktivieren oder deaktivieren'; }
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tuv_message_template ADD enabled TINYINT(1) DEFAULT 1 NOT NULL');
    }
    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tuv_message_template DROP enabled');
    }
}
