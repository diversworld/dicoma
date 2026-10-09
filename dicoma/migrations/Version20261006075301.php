<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261006075301 extends AbstractMigration
{
    private const ROLE_USER = 'ROLE_USER';
    private const ROLE_MEMBER = 'ROLE_MEMBER';

    private const LEGACY_ROLE_CUSTOMER = 'ROLE_CUSTOMER';
    private const LEGACY_ROLE_STUDENT = 'ROLE_STUDENT';

    public function getDescription(): string
    {
        return 'Migrates legacy DiCoMa user roles to the new club role model.';
    }

    public function up(Schema $schema): void
    {
        $users = $this->connection
            ->executeQuery('SELECT id, roles FROM `user`')
            ->fetchAllAssociative();

        foreach ($users as $user) {
            $roles = $this->decodeRoles($user['roles'] ?? null);

            /*
             * Jeder bestehende Benutzer wird zunächst Mitglied.
             *
             * ROLE_USER bleibt vorerst gespeichert, damit eventuell
             * noch vorhandene Controller/Templates während der
             * weiteren Umstellung kompatibel bleiben.
             */
            $roles[] = self::ROLE_MEMBER;

            /*
             * Die alten Rollen CUSTOMER und STUDENT beschrieben im
             * bisherigen System keine administrativen Berechtigungen.
             * Sie gehen deshalb in ROLE_MEMBER auf.
             */
            $roles = array_filter(
                $roles,
                static fn (string $role): bool => !in_array(
                    $role,
                    [
                        self::LEGACY_ROLE_CUSTOMER,
                        self::LEGACY_ROLE_STUDENT,
                    ],
                    true
                )
            );

            $roles = $this->normalizeRoles($roles);

            $this->connection->update(
                'user',
                [
                    'roles' => json_encode(
                        $roles,
                        JSON_THROW_ON_ERROR
                    ),
                ],
                [
                    'id' => $user['id'],
                ]
            );
        }
    }

    public function down(Schema $schema): void
    {
        /*
         * Eine automatische Rückmigration wäre fachlich nicht
         * eindeutig:
         *
         * ROLE_MEMBER könnte früher ROLE_USER, ROLE_CUSTOMER oder
         * ROLE_STUDENT gewesen sein.
         *
         * Deshalb entfernen wir bei einem Rollback lediglich die
         * neu ergänzte ROLE_MEMBER-Rolle und lassen alle anderen
         * Rollen unangetastet.
         */
        $users = $this->connection
            ->executeQuery('SELECT id, roles FROM `user`')
            ->fetchAllAssociative();

        foreach ($users as $user) {
            $roles = $this->decodeRoles($user['roles'] ?? null);

            $roles = array_filter(
                $roles,
                static fn (string $role): bool =>
                    $role !== self::ROLE_MEMBER
            );

            /*
             * ROLE_USER sicherstellen, damit ein zurückgerollter
             * Benutzer weiterhin dem alten Basismodell entspricht.
             */
            $roles[] = self::ROLE_USER;

            $roles = $this->normalizeRoles($roles);

            $this->connection->update(
                'user',
                [
                    'roles' => json_encode(
                        $roles,
                        JSON_THROW_ON_ERROR
                    ),
                ],
                [
                    'id' => $user['id'],
                ]
            );
        }
    }

    /**
     * @return list<string>
     */
    private function decodeRoles(mixed $value): array
    {
        if (!is_string($value) || $value === '') {
            return [];
        }

        try {
            $roles = json_decode(
                $value,
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (\JsonException) {
            /*
             * Defekte Legacy-Daten sollen nicht dazu führen,
             * dass ein Benutzer komplett ohne Basisrolle bleibt.
             */
            return [];
        }

        if (!is_array($roles)) {
            return [];
        }

        return $this->normalizeRoles($roles);
    }

    /**
     * @param array<mixed> $roles
     *
     * @return list<string>
     */
    private function normalizeRoles(array $roles): array
    {
        $roles = array_filter(
            $roles,
            static fn (mixed $role): bool =>
                is_string($role)
                && str_starts_with($role, 'ROLE_')
        );

        return array_values(
            array_unique($roles)
        );
    }
}