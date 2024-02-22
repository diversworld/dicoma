<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240218170705 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user DROP firstname, DROP lastname, DROP category, DROP street, DROP postal, DROP city, DROP birthdate, DROP mobile, DROP phone, DROP status, DROP create_account, DROP plain_password, CHANGE username username VARCHAR(180) NOT NULL, CHANGE password password VARCHAR(255) NOT NULL, CHANGE email email VARCHAR(150) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE `user` ADD firstname VARCHAR(50) NOT NULL, ADD lastname VARCHAR(50) NOT NULL, ADD category VARCHAR(20) NOT NULL, ADD street VARCHAR(100) DEFAULT NULL, ADD postal INT DEFAULT NULL, ADD city VARCHAR(100) DEFAULT NULL, ADD birthdate DATE DEFAULT NULL, ADD mobile VARCHAR(20) DEFAULT NULL, ADD phone VARCHAR(20) DEFAULT NULL, ADD status VARCHAR(16) NOT NULL, ADD create_account TINYINT(1) DEFAULT NULL, ADD plain_password VARCHAR(255) DEFAULT NULL, CHANGE username username VARCHAR(180) DEFAULT NULL, CHANGE password password VARCHAR(255) DEFAULT NULL, CHANGE email email VARCHAR(100) NOT NULL');
    }
}
