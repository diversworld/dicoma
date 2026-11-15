<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Club;
use App\Entity\ClubFunctionAssignment;
use App\Entity\FileAction;
use App\Entity\MediaFolder;
use App\Entity\MediaFolderPermission;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

final class FolderPermissions
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    public function can(User $user, Club $club, ?MediaFolder $folder, FileAction $action, ?\DateTimeImmutable $date = null): bool
    {
        if ($folder !== null && !self::sameClub($folder->getClub(), $club)) {
            return false;
        }
        $ancestors = [];
        $seen = [];
        for ($current = $folder; $current !== null; $current = $current->getParent()) {
            $id = $current->getId();
            if ($id === null || isset($seen[$id]) || !self::sameClub($current->getClub(), $club)) {
                throw new \LogicException('Ungültige Ordnerhierarchie.');
            }
            $seen[$id] = true;
            $ancestors[] = $id;
        }
        $date ??= new \DateTimeImmutable('today');
        $assignments = $this->em->getRepository(ClubFunctionAssignment::class)->findBy(['user' => $user]);
        foreach ($assignments as $assignment) {
            $function = $assignment->getClubFunction();
            if (!self::sameClub($function->getClub(), $club) || !$assignment->isEffectiveOn($date)) {
                continue;
            }
            $rules = $this->em->getRepository(MediaFolderPermission::class)->findBy(['clubFunction' => $function]);
            $ruleByFolder = [];
            foreach ($rules as $rule) {
                $id = $rule->getFolder()->getId();
                if ($id === null || isset($ruleByFolder[$id])) {
                    throw new \LogicException('Ungültige oder doppelte Ordnerregel.');
                }
                $ruleByFolder[$id] = $rule;
            }
            foreach ($ancestors as $id) {
                $decision = isset($ruleByFolder[$id]) ? $ruleByFolder[$id]->permission($action) : null;
                if ($decision === true) {
                    return true;
                }
                if ($decision === false) {
                    continue 2;
                }
            }
            if ($function->rootPermission($action) === true) {
                return true;
            }
        }
        return false;
    }

    private static function sameClub(Club $first, Club $second): bool
    {
        return $first === $second || ($first->getId() !== null && $first->getId() === $second->getId());
    }
}
