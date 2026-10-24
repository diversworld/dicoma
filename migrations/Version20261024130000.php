<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261024130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Phase 4: person-based course participants, guest attendance, payment ledger and archived certificates';
    }

    public function isTransactional(): bool { return false; }

    public function up(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform, 'Only MySQL/MariaDB is supported.');
        $this->abortIf((int) $this->connection->fetchOne('SELECT COUNT(*) FROM course_participant cp LEFT JOIN member m ON m.id = cp.member_id WHERE m.person_id IS NULL') > 0, 'Every existing participant must have a normalized person before migrating.');
        $this->abortIf((int) $this->connection->fetchOne('SELECT COUNT(*) FROM (SELECT cp.course_id, m.person_id FROM course_participant cp INNER JOIN member m ON m.id = cp.member_id GROUP BY cp.course_id, m.person_id HAVING COUNT(*) > 1) duplicates') > 0, 'Duplicate course/person registrations require explicit resolution before migrating; no training data was changed.');
        $table = $schema->getTable('course_participant');
        foreach ($table->getForeignKeys() as $key) {
            if ($key->getLocalColumns() === ['member_id']) {
                $this->addSql('ALTER TABLE course_participant DROP FOREIGN KEY ' . $key->getName());
            }
        }
        $this->addSql('ALTER TABLE courses ADD required_equipment LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE course_participant ADD person_id INT DEFAULT NULL, ADD fee_cents INT DEFAULT 0 NOT NULL, ADD certificate_number VARCHAR(120) DEFAULT NULL, ADD training_archive JSON DEFAULT NULL, ADD archived_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', MODIFY member_id INT DEFAULT NULL');
        $this->addSql('UPDATE course_participant cp INNER JOIN member m ON m.id = cp.member_id INNER JOIN courses c ON c.id = cp.course_id SET cp.person_id = m.person_id, cp.fee_cents = ROUND(c.course_fee * 100)');
        $this->addSql('ALTER TABLE course_participant MODIFY person_id INT NOT NULL, ADD CONSTRAINT fk_training_person FOREIGN KEY (person_id) REFERENCES person (id), ADD CONSTRAINT fk_training_member FOREIGN KEY (member_id) REFERENCES member (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_A3F15BC5217BBB47 ON course_participant (person_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_course_person ON course_participant (course_id, person_id)');
        $this->addSql('ALTER TABLE booking ADD course_participant_id INT DEFAULT NULL, ADD CONSTRAINT fk_booking_training_participant FOREIGN KEY (course_participant_id) REFERENCES course_participant (id)');
        $this->addSql('CREATE INDEX IDX_E00CEDDE62D0D72D ON booking (course_participant_id)');
        $this->addSql('UPDATE booking b INNER JOIN schedule s ON s.id = b.schedule_id INNER JOIN course_participant cp ON cp.course_id = s.courses_id AND cp.member_id = b.students_id SET b.course_participant_id = cp.id');
        $this->addSql('CREATE TABLE course_payment (id INT AUTO_INCREMENT NOT NULL, participant_id INT NOT NULL, amount_cents INT NOT NULL, method VARCHAR(20) NOT NULL, reference VARCHAR(120) NOT NULL, recorded_by VARCHAR(180) NOT NULL, recorded_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_65EB81E19D1C3019 (participant_id), UNIQUE INDEX uniq_course_payment_reference (participant_id, reference), PRIMARY KEY(id), CONSTRAINT fk_course_payment_participant FOREIGN KEY (participant_id) REFERENCES course_participant (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Guest identities, payments and archived evidence must not be discarded.');
    }
}
