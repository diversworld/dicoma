<?php
 declare(strict_types=1);
 namespace DoctrineMigrations;
 use Doctrine\DBAL\Schema\Schema;
 use Doctrine\Migrations\AbstractMigration;
 final class Version20261020170000 extends AbstractMigration {
 public function getDescription():string{return 'Responsible course instructor';}
 public function up(Schema $schema):void {
 $this->addSql('ALTER TABLE courses ADD lead_instructor_id INT DEFAULT NULL');
 $this->addSql('ALTER TABLE courses ADD CONSTRAINT FK_COURSE_LEAD_INSTRUCTOR FOREIGN KEY (lead_instructor_id) REFERENCES member (id) ON DELETE SET NULL');
 $this->addSql('CREATE INDEX IDX_COURSE_LEAD_INSTRUCTOR ON courses (lead_instructor_id)');
 }
 public function down(Schema $schema):void {
 $this->addSql('ALTER TABLE courses DROP FOREIGN KEY FK_COURSE_LEAD_INSTRUCTOR');
 $this->addSql('DROP INDEX IDX_COURSE_LEAD_INSTRUCTOR ON courses');
 $this->addSql('ALTER TABLE courses DROP lead_instructor_id');
 }
 }
