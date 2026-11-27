<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Club;
use App\Entity\ClubFunction;
use App\Entity\FinanceAccount;
use App\Entity\MembershipPolicy;
use App\Entity\PrivacyPolicy;
use App\Entity\WebsiteDisplaySettings;
use App\Finance\Skr42Chart;
use App\Security\ModulePermission;
use App\Enum\PermissionAction;
use App\Enum\PermissionModule;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

final class InstallationCatalog
{
    public const VERSION = 2026112701;

    public function __construct(private readonly EntityManagerInterface $em) {}

    /** @return list<array{id:string,label:string,installed:bool}> */
    public function components(Club $club): array
    {
        $functions = [];
        foreach ($this->functionDefinitions() as $definition) {
            $functions[] = [
                'id' => 'function:'.$definition['name'],
                'label' => 'Vereinsfunktion: '.$definition['name'],
                'installed' => $this->em->getRepository(ClubFunction::class)->count(['club' => $club, 'name' => $definition['name']]) > 0,
            ];
        }
        return [
            ...$functions,
            [
                'id' => 'finance:skr42',
                'label' => 'SKR42- und interne Kassenkonten',
                'installed' => $this->isChartInstalled($club),
            ],
            [
                'id' => 'policy:membership',
                'label' => 'Mitgliedschafts- und Mahnrichtlinie',
                'installed' => $this->em->getRepository(MembershipPolicy::class)->count(['club' => $club]) > 0,
            ],
            [
                'id' => 'policy:privacy',
                'label' => 'Datenschutz- und Aufbewahrungsrichtlinie',
                'installed' => $this->em->getRepository(PrivacyPolicy::class)->count(['club' => $club]) > 0,
            ],
            [
                'id' => 'website:display',
                'label' => 'Website-Anzeigeeinstellungen',
                'installed' => $this->em->find(WebsiteDisplaySettings::class, $club->getId()) !== null,
            ],
        ];
    }

    /** @return list<string> */
    public function installMissing(Club $club, ?string $only = null): array
    {
        $installed = [];
        foreach ($this->functionDefinitions() as $definition) {
            $id = 'function:'.$definition['name'];
            if ($only !== null && $id !== $only) { continue; }
            if ($this->em->getRepository(ClubFunction::class)->count(['club' => $club, 'name' => $definition['name']]) > 0) { continue; }
            $function = new ClubFunction($club, $definition['name']);
            $function->setPermissions($definition['roles']);
            $function->setModulePermissions($definition['modules']);
            $function->setSuccessorRequired((bool) ($definition['successor'] ?? false));
            $this->em->persist($function);
            $installed[] = $id;
        }
        if (($only === null || $only === 'finance:skr42') && !$this->isChartInstalled($club)) {
            foreach (Skr42Chart::accounts() as $row) {
                if ($this->em->getRepository(FinanceAccount::class)->findOneBy(['club' => $club, 'code' => $row['code']]) !== null) { continue; }
                $this->em->persist(new FinanceAccount($club, $row['code'], $row['name'], $row['type'], Skr42Chart::NAME, $row['sphere'], $row['usage'], Skr42Chart::SOURCE));
            }
            $installed[] = 'finance:skr42';
        }
        if (($only === null || $only === 'policy:membership') && $this->em->getRepository(MembershipPolicy::class)->count(['club' => $club]) === 0) {
            $this->em->persist(new MembershipPolicy($club));
            $installed[] = 'policy:membership';
        }
        if (($only === null || $only === 'policy:privacy') && $this->em->getRepository(PrivacyPolicy::class)->count(['club' => $club]) === 0) {
            $this->em->persist(new PrivacyPolicy($club));
            $installed[] = 'policy:privacy';
        }
        if (($only === null || $only === 'website:display') && $this->em->find(WebsiteDisplaySettings::class, $club->getId()) === null) {
            $this->em->persist(new WebsiteDisplaySettings($club));
            $installed[] = 'website:display';
        }
        return array_values(array_unique($installed));
    }

    /** @return list<array{name:string,roles:list<string>,modules:list<string>,successor?:bool}> */
    private function functionDefinitions(): array
    {
        $all = ModulePermission::choices(true);
        $module = fn (PermissionModule $permissionModule, PermissionAction ...$actions): array => array_map(
            static fn (PermissionAction $action): string => ModulePermission::key($permissionModule, $action),
            $actions,
        );
        $rw = [PermissionAction::READ, PermissionAction::CREATE, PermissionAction::EDIT];
        return [
            ['name' => 'Administrator', 'roles' => [User::ROLE_ADMIN], 'modules' => array_values($all), 'successor' => true],
            ['name' => 'Vorstand', 'roles' => [User::ROLE_BOARD], 'modules' => array_values(array_unique([...$module(PermissionModule::MEMBERSHIP, ...$rw), ...$module(PermissionModule::GOVERNANCE, ...$rw), ...$module(PermissionModule::FINANCE, PermissionAction::READ), ...$module(PermissionModule::WEBSITE, ...$rw), ...$module(PermissionModule::FILES, ...$rw)])), 'successor' => true],
            ['name' => 'Kassenwart', 'roles' => [User::ROLE_TREASURER], 'modules' => array_values(array_unique([...$module(PermissionModule::FINANCE, ...$rw), ...$module(PermissionModule::ACCOUNTING, ...$rw)])), 'successor' => true],
            ['name' => 'Schriftführung', 'roles' => [User::ROLE_SECRETARY], 'modules' => array_values(array_unique([...$module(PermissionModule::GOVERNANCE, ...$rw), ...$module(PermissionModule::FILES, PermissionAction::READ, PermissionAction::CREATE)]))],
            ['name' => 'Pressewart', 'roles' => [User::ROLE_PRESS_OFFICER], 'modules' => array_values(array_unique([...$module(PermissionModule::WEBSITE, ...[...$rw, PermissionAction::DELETE]), ...$module(PermissionModule::FILES, ...[...$rw, PermissionAction::DELETE])]))],
            ['name' => 'Ausbildungsleiter', 'roles' => [User::ROLE_TRAINING_MANAGER], 'modules' => $module(PermissionModule::TRAINING, ...[...$rw, PermissionAction::DELETE])],
            ['name' => 'Kursverwaltung', 'roles' => [User::ROLE_COURSE_ADMIN], 'modules' => $module(PermissionModule::TRAINING, ...$rw)],
            ['name' => 'Ausbilder / Trainer', 'roles' => [User::ROLE_INSTRUCTOR], 'modules' => $module(PermissionModule::TRAINING, PermissionAction::READ)],
            ['name' => 'Gerätewart', 'roles' => [User::ROLE_EQUIPMENT_MANAGER], 'modules' => $module(PermissionModule::EQUIPMENT, ...$rw)],
            ['name' => 'Gerätekasse', 'roles' => [User::ROLE_EQUIPMENT_CASH], 'modules' => $module(PermissionModule::EQUIPMENT_CASH, ...$rw)],
            ['name' => 'Kompressorbediener', 'roles' => [User::ROLE_COMPRESSOR_OPERATOR], 'modules' => $module(PermissionModule::EQUIPMENT, PermissionAction::READ, PermissionAction::EDIT)],
            ['name' => 'Thekenleitung', 'roles' => [User::ROLE_BAR_MANAGER], 'modules' => array_values(array_unique([...$module(PermissionModule::BAR_DUTY, ...$rw), ...$module(PermissionModule::BAR_CASH, ...$rw)]))],
        ];
    }

    private function isChartInstalled(Club $club): bool
    {
        $repository = $this->em->getRepository(FinanceAccount::class);
        foreach (Skr42Chart::accounts() as $row) {
            if ($repository->findOneBy(['club' => $club, 'code' => $row['code']]) === null) {
                return false;
            }
        }
        return true;
    }
}
