<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\EquipmentAssetRecord;
use App\Entity\EquipmentItem;
use App\Entity\Regulator;
use App\Entity\Tank;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

final class EquipmentInventory
{
    public const TYPES = ['equipment' => EquipmentItem::class, 'regulator' => Regulator::class, 'tank' => Tank::class];

    public function __construct(private readonly EntityManagerInterface $em) {}

    public static function field(EquipmentItem|Regulator|Tank $asset): string
    {
        return $asset instanceof EquipmentItem ? 'equipmentItem' : ($asset instanceof Regulator ? 'regulator' : 'tank');
    }

    public static function physicalAsset(EquipmentItem|Regulator|Tank $asset): EquipmentItem|Regulator|Tank
    {
        return $asset instanceof EquipmentItem && $asset->getTank() !== null ? $asset->getTank() : $asset;
    }

    public function asset(string $type, int $id, int $clubId, bool $lock = false): EquipmentItem|Regulator|Tank
    {
        if (!isset(self::TYPES[$type])) { throw new \DomainException('Unbekannter Inventarbereich.'); }
        $asset = $this->em->find(self::TYPES[$type], $id);
        if (!$asset) { throw new \DomainException('Inventargegenstand nicht gefunden.'); }
        if ($lock) {
            $physical = self::physicalAsset($asset);
            $this->em->refresh($physical, LockMode::PESSIMISTIC_WRITE);
            if ($physical !== $asset) { $this->em->refresh($asset, LockMode::PESSIMISTIC_WRITE); }
        }
        if ($asset->getClub()?->getId() !== $clubId || self::physicalAsset($asset)->getClub()?->getId() !== $clubId) { throw new \DomainException('Inventargegenstand nicht im eigenen Verein.'); }
        return $asset;
    }

    public function findRecord(EquipmentItem|Regulator|Tank $asset): ?EquipmentAssetRecord
    {
        $asset = self::physicalAsset($asset);
        return $this->em->getRepository(EquipmentAssetRecord::class)->findOneBy([self::field($asset) => $asset]);
    }

    /** The caller must hold the asset row lock when creating or changing its record. */
    public function record(EquipmentItem|Regulator|Tank $asset): EquipmentAssetRecord
    {
        $asset = self::physicalAsset($asset);
        $record = $this->findRecord($asset);
        if ($record === null) {
            $record = new EquipmentAssetRecord($asset);
            $this->em->persist($record);
        }
        return $record;
    }

    public function dueAt(EquipmentItem|Regulator|Tank $asset): ?\DateTimeInterface
    {
        $asset = self::physicalAsset($asset);
        if ($asset instanceof Tank) { return $asset->getNextCheckDate(); }
        if ($asset instanceof Regulator) { return $asset->getNextServiceAt(); }
        return $this->findRecord($asset)?->getNextInspectionAt();
    }

    public function requiresApproval(EquipmentItem|Regulator|Tank $asset): bool
    {
        return $this->findRecord($asset)?->isApprovalRequired() ?? false;
    }

    public static function feeCents(EquipmentItem|Regulator|Tank $asset): int
    {
        if (!preg_match('/^\d{1,8}(?:\.\d{1,2})?$/D', $asset->getRentalFee())) {
            throw new \DomainException('Leihgebühr fehlt oder ist ungültig.');
        }
        return TuvPriceCalculator::cents($asset->getRentalFee());
    }
}
