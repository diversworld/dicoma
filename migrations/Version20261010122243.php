<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261010122243 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Bewahrt die fachlichen Unique-Constraints der Ausbildungsverwaltung.';
    }

    public function up(Schema $schema): void
    {
        // Keine Schemaänderung.
        //
        // Die ursprüngliche automatisch generierte Migration
        // wollte sechs fachlich erforderliche Unique-Indizes
        // entfernen.
        //
        // Diese Constraints bleiben Bestandteil des Datenmodells.
    }

    public function down(Schema $schema): void
    {
        // Keine Schemaänderung.
    }
}
