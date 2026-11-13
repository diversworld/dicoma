<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use App\Enum\MembershipStatus;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:functions:check-migration', description: 'Prüft gespeicherte Rollen und Zeiträume vor einer Funktionsmigration, ohne Daten zu ändern.')]
final class CheckFunctionMigrationCommand extends Command
{
    public function __construct(private readonly Connection $connection)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $knownRoles = array_values(User::getAvailableRoles());
        $counts = [
            'Konten' => 0,
            'Konten ohne Mitgliedschaft' => 0,
            'Konten mit nicht aktiver Mitgliedschaft' => 0,
            'Konten ohne Vereinszuordnung' => 0,
            'Konten mit Superadmin-Notfallrolle' => 0,
            'Gespeicherte Funktionsrollenzuordnungen' => 0,
            'Gespeicherte Gültigkeitszeiträume' => 0,
        ];
        $issues = [];
        $seen = [];
        $rows = $this->connection->executeQuery(
            'SELECT u.id, u.roles, u.role_validity, m.id AS member_id, m.club_id, m.membership_status, m.left_at
             FROM `user` u LEFT JOIN member m ON m.user_id = u.id ORDER BY u.id'
        );

        while ($row = $rows->fetchAssociative()) {
            $id = (int) $row['id'];
            if (isset($seen[$id])) {
                $issues[] = sprintf('Konto-ID %d: Mehrere Mitgliedschaften sind mit diesem Konto verknüpft.', $id);
                continue;
            }
            $seen[$id] = true;
            ++$counts['Konten'];
            if ($row['member_id'] === null) {
                ++$counts['Konten ohne Mitgliedschaft'];
            } else {
                if ($row['membership_status'] !== MembershipStatus::ACTIVE->value || $row['left_at'] !== null) {
                    ++$counts['Konten mit nicht aktiver Mitgliedschaft'];
                }
                if ($row['club_id'] === null) {
                    ++$counts['Konten ohne Vereinszuordnung'];
                }
            }

            try {
                $roles = json_decode((string) $row['roles'], false, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                $issues[] = sprintf('Konto-ID %d: Rollen sind kein gültiges JSON.', $id);
                continue;
            }
            if (!is_array($roles)) {
                $issues[] = sprintf('Konto-ID %d: Rollen sind keine Liste.', $id);
                continue;
            }
            $roleSet = [];
            $invalidRoles = false;
            foreach ($roles as $role) {
                if (!is_string($role) || !in_array($role, $knownRoles, true) || isset($roleSet[$role])) {
                    $invalidRoles = true;
                    continue;
                }
                $roleSet[$role] = true;
            }
            if ($invalidRoles) {
                $issues[] = sprintf('Konto-ID %d: Rollenliste enthält unbekannte, ungültige oder doppelte Einträge.', $id);
            }
            if (isset($roleSet[User::ROLE_SUPER_ADMIN])) {
                ++$counts['Konten mit Superadmin-Notfallrolle'];
            }
            $counts['Gespeicherte Funktionsrollenzuordnungen'] += count(array_diff(
                array_keys($roleSet),
                [User::ROLE_USER, User::ROLE_MEMBER, User::ROLE_SUPER_ADMIN]
            ));

            if ($row['role_validity'] === null) {
                continue;
            }
            try {
                $windows = json_decode((string) $row['role_validity'], false, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                $issues[] = sprintf('Konto-ID %d: Rollengültigkeit ist kein gültiges JSON.', $id);
                continue;
            }
            if ($windows === []) {
                continue;
            }
            if (!$windows instanceof \stdClass) {
                $issues[] = sprintf('Konto-ID %d: Rollengültigkeit ist kein Objekt.', $id);
                continue;
            }
            $validity = [];
            $invalidWindows = false;
            foreach (get_object_vars($windows) as $role => $window) {
                if (!$window instanceof \stdClass) {
                    $invalidWindows = true;
                    continue;
                }
                $fields = get_object_vars($window);
                if (count($fields) !== 2 || !array_key_exists('startsAt', $fields) || !array_key_exists('endsAt', $fields)) {
                    $invalidWindows = true;
                    continue;
                }
                $validity[$role] = $fields;
            }
            $counts['Gespeicherte Gültigkeitszeiträume'] += count(get_object_vars($windows));
            try {
                $user = (new User())->setRoles(array_keys($roleSet));
                $user->setRoleValidity($validity);
            } catch (\InvalidArgumentException) {
                $invalidWindows = true;
            }
            if ($invalidWindows) {
                $issues[] = sprintf('Konto-ID %d: Rollengültigkeit ist ungültig, nicht zugeordnet oder enthält zusätzliche Felder.', $id);
            }
        }

        foreach ($counts as $label => $count) {
            $output->writeln(sprintf('%s: %d', $label, $count));
        }
        foreach ($issues as $issue) {
            $output->writeln('<error>'.$issue.'</error>');
        }
        $output->writeln('Doppelte JSON-Objektschlüssel und parallele Änderungen werden nicht geprüft.');
        $output->writeln('Keine Daten geändert. Die Prüfung ersetzt weder die Migration noch die fachliche Freigabe.');

        return $issues === [] ? Command::SUCCESS : Command::FAILURE;
    }
}
