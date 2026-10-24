<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261023100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Extract central person records without merging identities; retain legacy columns until verified';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform, 'Only MySQL/MariaDB is supported.');
        $this->abortIf($schema->hasTable('person'), 'person already exists: inspect a partially applied migration before continuing.');

        $this->addSql("CREATE TABLE person (id INT AUTO_INCREMENT NOT NULL, firstname VARCHAR(100) NOT NULL, lastname VARCHAR(100) NOT NULL, birthday DATE DEFAULT NULL, email VARCHAR(100) DEFAULT NULL, mobile VARCHAR(25) DEFAULT NULL, phone VARCHAR(25) DEFAULT NULL, street VARCHAR(100) DEFAULT NULL, postal_code VARCHAR(20) DEFAULT NULL, city VARCHAR(120) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('ALTER TABLE member ADD person_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE `user` ADD person_id INT DEFAULT NULL');
        $this->addSql('INSERT INTO person (id, firstname, lastname, birthday, email, mobile, phone, street, postal_code, city) SELECT id, firstname, lastname, birthday, email, mobile, phone, street, postal_code, city FROM member');
        $this->addSql('UPDATE member SET person_id = id');
        $this->addSql('UPDATE `user` u INNER JOIN member m ON m.user_id = u.id SET u.person_id = m.person_id');
        $this->addSql('ALTER TABLE member MODIFY person_id INT NOT NULL');
        $this->addSql('CREATE INDEX IDX_70E4FA78217BBB47 ON member (person_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_member_person_club ON member (person_id, club_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649217BBB47 ON `user` (person_id)');
        $this->addSql('ALTER TABLE member ADD CONSTRAINT FK_70E4FA78217BBB47 FOREIGN KEY (person_id) REFERENCES person (id)');
        $this->addSql('ALTER TABLE `user` ADD CONSTRAINT FK_8D93D649217BBB47 FOREIGN KEY (person_id) REFERENCES person (id) ON DELETE SET NULL');
        foreach ($schema->getTable('member')->getForeignKeys() as $foreignKey) {
            if ($foreignKey->getLocalColumns() === ['user_id']) {
                $this->addSql('ALTER TABLE member DROP FOREIGN KEY ' . $this->connection->quoteIdentifier($foreignKey->getName()));
            }
        }
        $this->addSql('ALTER TABLE member ADD CONSTRAINT FK_70E4FA78A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Person records may include guests and new contact data. Restore a verified backup instead of deleting identities.');
    }
}
