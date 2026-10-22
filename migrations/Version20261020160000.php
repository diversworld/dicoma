<?php
declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20261020160000 extends AbstractMigration {
 public function getDescription(): string {return 'Release course schedule to participants';}
 public function up(Schema $schema): void {$this->addSql('ALTER TABLE courses ADD schedule_published_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');}
 public function down(Schema $schema): void {$this->addSql('ALTER TABLE courses DROP schedule_published_at');}
}
