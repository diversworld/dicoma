<?php
declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20261017210000 extends AbstractMigration
{
    public function getDescription():string { return 'Konfigurierbare TÜV-Benachrichtigungsvorlagen'; }
    public function up(Schema $schema):void { $this->addSql("CREATE TABLE tuv_message_template (kind VARCHAR(32) NOT NULL, subject VARCHAR(200) NOT NULL, body LONGTEXT NOT NULL, PRIMARY KEY(kind)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB"); }
    public function down(Schema $schema):void { $this->addSql('DROP TABLE tuv_message_template'); }
}
