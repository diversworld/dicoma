<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Club;
use App\Entity\ClubFunction;
use App\Entity\ClubFunctionAssignment;
use App\Entity\FileAction;
use App\Entity\MediaFolder;
use App\Entity\MediaFolderPermission;
use App\Entity\User;
use App\Enum\PermissionAction;
use App\Enum\PermissionModule;
use App\Security\ModulePermission;
use App\Security\LegacyFunctionPermissions;
use App\Service\MembershipRoleContinuity;
use App\Service\SingleClubMode;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;

#[AsCommand(name: 'app:functions:migrate-roles', description: 'Übernimmt direkte Funktionsrollen und Zeiträume in Vereinsfunktionen.')]
final class MigrateFunctionRolesCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SingleClubMode $singleClub,
        private readonly MembershipRoleContinuity $continuity,
        private readonly RoleHierarchyInterface $hierarchy,
        private readonly LegacyFunctionPermissions $legacyPermissions,
        private readonly CheckFunctionMigrationCommand $check,
        #[Autowire('%env(bool:CLUB_FUNCTION_MEMBERSHIP_ENABLED)%')] private readonly bool $enabled,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('confirm', null, InputOption::VALUE_NONE, 'Bestätigt die Übernahme nach Backup und überprüftem Restore.');
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Prüft die Übernahme in einer Transaktion und verwirft alle Datenänderungen.');
        $this->addOption('merge-training-manager', null, InputOption::VALUE_NONE, 'Übernimmt Ausbildungsleiter in die geprüfte bestehende Funktion und aktiviert deren Nachfolgepflicht.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dryRun = (bool) $input->getOption('dry-run');
        $mergeTrainingManager = (bool) $input->getOption('merge-training-manager');
        if ($dryRun && $input->getOption('confirm')) {
            $output->writeln('<error>--dry-run und --confirm dürfen nicht gleichzeitig verwendet werden.</error>');
            return Command::FAILURE;
        }
        if (!$dryRun && (!$input->getOption('confirm') || !$this->enabled)) {
            $output->writeln('<error>Die Übernahme benötigt --confirm und das aktivierte Funktionsmodell. Zuerst Backup und Restore prüfen.</error>');
            return Command::FAILURE;
        }
        $club = $this->singleClub->club();
        $clubId = $club->getId();
        $connection = $this->em->getConnection();
        if ($connection->isTransactionActive() || !$connection->isAutoCommit()) {
            $output->writeln('<error>Die Übernahme benötigt eine eigene, nicht bereits aktive Transaktion.</error>');
            return Command::FAILURE;
        }
        if ($connection->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
            $tables = ['club', 'user', 'member', 'club_function', 'club_function_assignment', 'media_folder', 'media_folder_permission'];
            foreach ($tables as $table) {
                $engine = $connection->fetchOne(
                    'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
                    [$table],
                );
                if (!is_string($engine) || strcasecmp($engine, 'InnoDB') !== 0) {
                    $output->writeln(sprintf('<error>Die Übernahme benötigt InnoDB für Tabelle %s.</error>', $table));
                    return Command::FAILURE;
                }
            }
        }
        $connection->beginTransaction();
        $completed = false;
        try {
            $count = (function () use ($club, $clubId, $mergeTrainingManager): int {
                $this->continuity->lockClub($club);
                $connection = $this->em->getConnection();
                $lock = $connection->getDatabasePlatform() instanceof AbstractMySQLPlatform ? ' FOR UPDATE' : '';
                $connection->fetchFirstColumn('SELECT id FROM `user` ORDER BY id'.$lock);
                $connection->fetchFirstColumn('SELECT id FROM member ORDER BY id'.$lock);
                $report = new BufferedOutput();
                if ($this->check->run(new ArrayInput([]), $report) !== Command::SUCCESS) {
                    throw new \DomainException("Die Vorprüfung ist fehlgeschlagen:\n".$report->fetch());
                }
                $rows = $connection->fetchAllAssociative(
                    'SELECT u.id, u.roles, u.role_validity, m.club_id, m.membership_status, m.left_at, m.archived_at
                     FROM `user` u LEFT JOIN member m ON m.user_id = u.id ORDER BY u.id'
                );
                foreach ($rows as $row) {
                    $this->assertUnambiguousValidity($row['role_validity'], (int) $row['id']);
                    $roles = json_decode((string) $row['roles'], true, 512, JSON_THROW_ON_ERROR);
                    $functions = array_diff($roles, [User::ROLE_USER, User::ROLE_MEMBER, User::ROLE_SUPER_ADMIN]);
                    if (($functions !== [] || in_array(User::ROLE_SUPER_ADMIN, $roles, true))
                        && ($row['club_id'] === null || (int) $row['club_id'] !== $clubId
                            || $row['membership_status'] !== 'active' || $row['left_at'] !== null || $row['archived_at'] !== null)) {
                        throw new \DomainException(sprintf('Konto-ID %d mit Funktions- oder Superadminrolle hat keine aktive Mitgliedschaft im Zielverein. Keine Daten wurden übernommen.', $row['id']));
                    }
                }
                $this->em->clear();
                $club = $this->em->find(Club::class, $clubId) ?? throw new \LogicException('Zielverein nicht vorhanden.');
                $labels = array_flip(User::getAvailableRoles());
                $count = 0;
                foreach ($rows as $row) {
                    $user = $this->em->find(User::class, (int) $row['id']) ?? throw new \LogicException('Konto nicht vorhanden.');
                    $stored = $user->getStoredRoles();
                    $validity = $user->getRoleValidity();
                    foreach (array_diff($stored, [User::ROLE_USER, User::ROLE_MEMBER, User::ROLE_SUPER_ADMIN]) as $role) {
                        $folderAccess = in_array(User::ROLE_PRESS_OFFICER, $this->hierarchy->getReachableRoleNames([$role]), true);
                        $function = $this->legacyFunction($club, $labels[$role], [$role], true, $folderAccess, $mergeTrainingManager && $role === User::ROLE_TRAINING_MANAGER);
                        $this->assign($function, $user, $validity[$role] ?? ['startsAt' => null, 'endsAt' => null]);
                        ++$count;
                    }
                    if (in_array(User::ROLE_SUPER_ADMIN, $stored, true)) {
                        $function = $this->legacyFunction($club, 'Dateiverwaltung (Superadministrator)', [], false, true);
                        $this->assign($function, $user, $validity[User::ROLE_SUPER_ADMIN] ?? ['startsAt' => null, 'endsAt' => null]);
                    }
                    $user->setRoles(array_values(array_intersect($stored, [User::ROLE_USER, User::ROLE_MEMBER, User::ROLE_SUPER_ADMIN])));
                }
                $this->em->flush();
                return $count;
            })();
            if ($dryRun) {
                $connection->rollBack();
            } else {
                $connection->commit();
            }
            $completed = true;
        } catch (\DomainException $exception) {
            $output->writeln('<error>'.$exception->getMessage().'</error>');
            return Command::FAILURE;
        } finally {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
            if ($dryRun || !$completed) {
                $this->em->clear();
            }
        }
        if ($dryRun) {
            $output->writeln(sprintf('Probelauf erfolgreich: %d direkte Funktionsrollenzuordnungen geprüft; alle Datenänderungen wurden zurückgerollt.', $count));
            $output->writeln('Keine Rollen übernommen und kein Funktionsmodell aktiviert. Datenbanksequenzen können sich geändert haben; Probelauf auf einer isolierten Datenbankkopie ausführen.');
            return Command::SUCCESS;
        }
        $output->writeln(sprintf('%d direkte Funktionsrollenzuordnungen übernommen; die Superadmin-Notfallrolle bleibt erhalten.', $count));
        return Command::SUCCESS;
    }

    private function legacyFunction(Club $club, string $name, array $roles, bool $successorRequired, bool $folderAccess, bool $mergeTrainingManager = false): ClubFunction
    {
        $function = $this->em->getRepository(ClubFunction::class)->findOneBy(['club' => $club, 'name' => $name]);
        $folders = $this->em->getRepository(MediaFolder::class)->findBy(['club' => $club, 'parent' => null]);
        $modulePermissions = $this->legacyPermissions->forRoles($roles);
        if ($mergeTrainingManager) {
            if ($function === null || $roles !== [User::ROLE_TRAINING_MANAGER] || $folderAccess) {
                throw new \DomainException('Die geprüfte Funktion „Ausbildungsleiter“ ist nicht vorhanden oder widerspricht der Übernahme.');
            }
            $modulePermissions = array_map(static fn (PermissionAction $action): string => ModulePermission::key(PermissionModule::TRAINING, $action), PermissionAction::cases());
            $existingModules = $function->getModulePermissions();
            $rules = $this->em->getRepository(MediaFolderPermission::class)->count(['clubFunction' => $function]);
            foreach (FileAction::cases() as $action) {
                if ($function->rootPermission($action) !== null) {
                    throw new \DomainException('Die Funktion „Ausbildungsleiter“ wurde geändert und widerspricht der bestätigten Übernahme.');
                }
            }
            $newMerge = $function->getPermissions() === [] && !$function->isSuccessorRequired()
                && $this->em->getRepository(ClubFunctionAssignment::class)->count(['clubFunction' => $function]) === 0;
            $alreadyMerged = $function->getPermissions() === $roles && $function->isSuccessorRequired();
            if ((!$newMerge && !$alreadyMerged) || count($existingModules) !== count($modulePermissions)
                || array_diff($existingModules, $modulePermissions) !== [] || $rules !== 0
                || $function->getStartsAt() !== null || $function->getEndsAt() !== null) {
                throw new \DomainException('Die Funktion „Ausbildungsleiter“ wurde geändert und widerspricht der bestätigten Übernahme.');
            }
            if ($newMerge) {
                $function->setPermissions($roles);
                $function->setSuccessorRequired(true);
                $this->em->flush();
            }
            return $function;
        }
        if ($function !== null) {
            $existingModulePermissions = $function->getModulePermissions();
            if ($function->getPermissions() !== $roles || count($existingModulePermissions) !== count($modulePermissions)
                || array_diff($existingModulePermissions, $modulePermissions) !== [] || $function->isSuccessorRequired() !== $successorRequired
                || $function->getStartsAt() !== null || $function->getEndsAt() !== null) {
                throw new \DomainException('Eine bestehende Funktion mit dem Namen „'.$name.'“ widerspricht der Rollenübernahme.');
            }
            $rules = $this->em->getRepository(MediaFolderPermission::class)->findBy(['clubFunction' => $function]);
            foreach ($rules as $rule) {
                if ($rule->getFolder()->getParent() !== null || $rule->getFolder()->getClub()->getId() !== $club->getId()) {
                    throw new \DomainException('Die Ordnerrechte der Funktion „'.$name.'“ widersprechen der Rollenübernahme.');
                }
            }
            foreach ($folders as $folder) {
                $rule = $this->em->getRepository(MediaFolderPermission::class)->findOneBy(['clubFunction' => $function, 'folder' => $folder]);
                foreach (FileAction::cases() as $action) {
                    if (($rule?->permission($action) === true) !== $folderAccess) {
                        throw new \DomainException('Die Ordnerrechte der Funktion „'.$name.'“ widersprechen der Rollenübernahme.');
                    }
                }
            }
            return $function;
        }
        $function = new ClubFunction($club, $name);
        $function->setPermissions($roles);
        $function->setModulePermissions($modulePermissions);
        $function->setSuccessorRequired($successorRequired);
        $this->em->persist($function);
        if ($folderAccess) {
            foreach ($folders as $folder) {
                $rule = new MediaFolderPermission($function, $folder);
                foreach (FileAction::cases() as $action) {
                    $rule->setPermission($action, true);
                }
                $this->em->persist($rule);
            }
        }
        $this->em->flush();
        return $function;
    }

    /** @param array{startsAt: ?string, endsAt: ?string} $window */
    private function assign(ClubFunction $function, User $user, array $window): void
    {
        $start = $window['startsAt'] === null ? null : new \DateTimeImmutable($window['startsAt']);
        $end = $window['endsAt'] === null ? null : new \DateTimeImmutable($window['endsAt']);
        $assignment = $this->em->getRepository(ClubFunctionAssignment::class)->findOneBy(['clubFunction' => $function, 'user' => $user]);
        if ($assignment !== null && ($assignment->getStartsAt()?->format('Y-m-d') !== $window['startsAt']
            || $assignment->getEndsAt()?->format('Y-m-d') !== $window['endsAt'])) {
            throw new \DomainException('Eine bestehende Funktionszuordnung widerspricht dem gespeicherten Rollenzeitraum.');
        }
        if ($assignment === null) {
            $assignment = new ClubFunctionAssignment($function, $user);
            $assignment->setValidity($start, $end);
            $this->em->persist($assignment);
        }
    }

    private function assertUnambiguousValidity(?string $json, int $userId): void
    {
        if ($json === null || $json === '[]') {
            return;
        }
        if (str_contains($json, '\\')) {
            throw new \DomainException(sprintf('Konto-ID %d: Rollengültigkeit enthält mehrdeutige JSON-Escapes.', $userId));
        }
        $windows = json_decode($json, false, 512, JSON_THROW_ON_ERROR);
        if (!$windows instanceof \stdClass) {
            throw new \DomainException(sprintf('Konto-ID %d: Ungültige Rollengültigkeit.', $userId));
        }
        preg_match_all('/"([^"]+)"\s*:/', $json, $matches);
        $keys = array_count_values($matches[1]);
        $count = count(get_object_vars($windows));
        if (($keys['startsAt'] ?? 0) !== $count || ($keys['endsAt'] ?? 0) !== $count) {
            throw new \DomainException(sprintf('Konto-ID %d: Doppelte oder fehlende Felder in der Rollengültigkeit.', $userId));
        }
        foreach (array_keys(get_object_vars($windows)) as $role) {
            if (($keys[$role] ?? 0) !== 1) {
                throw new \DomainException(sprintf('Konto-ID %d: Doppelte Rolle in der Rollengültigkeit.', $userId));
            }
        }
    }
}
