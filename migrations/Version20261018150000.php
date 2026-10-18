<?php
declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261018150000 extends AbstractMigration
{
    public function getDescription():string{return 'TÜV-Mailversand: Fehlerversuche und Versandfehler protokollieren';}
    public function up(Schema $schema):void
    {
        $this->addSql('ALTER TABLE tuv_notification ADD attempt_count INT DEFAULT 0 NOT NULL, ADD last_error LONGTEXT DEFAULT NULL, ADD last_attempt_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }
    public function down(Schema $schema):void
    {
        $this->addSql('ALTER TABLE tuv_notification DROP attempt_count, DROP last_error, DROP last_attempt_at');
    }
}
