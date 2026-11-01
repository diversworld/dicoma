<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Club;
use App\Entity\EquipmentBooking;
use App\Entity\EquipmentItem;
use App\Entity\EquipmentReservation;
use App\Entity\Member;
use App\Entity\Regulator;
use App\Entity\Tank;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class AdminEquipmentScope
{
    public function __construct(private readonly AdminClubContext $context) {}

    public function club(): Club
    {
        return $this->context->getClub() ?? throw new AccessDeniedException('Bitte zuerst einen Verein auswählen.');
    }

    public function assertClub(?Club $club): void
    {
        $id = $this->club()->getId();
        if ($id === null || $club?->getId() !== $id) {
            throw new AccessDeniedException('Ausrüstung gehört nicht zum ausgewählten Verein.');
        }
    }

    public function assertMember(?Member $member): void
    {
        $this->assertClub($member?->getClub());
    }

    public function assertAsset(EquipmentItem|Regulator|Tank|null $asset): void
    {
        $this->assertClub($asset?->getClub());
        if ($asset instanceof EquipmentItem && $asset->getTank() !== null) {
            $this->assertClub($asset->getTank()->getClub());
        }
    }

    public function assertBooking(EquipmentBooking $booking): void
    {
        $this->assertMember($booking->getMember());
        foreach ($booking->getItems() as $item) {
            $assets = array_values(array_filter([$item->getEquipmentItem(), $item->getRegulator(), $item->getTank()]));
            if (count($assets) !== 1) {
                throw new AccessDeniedException('Ungültige Ausrüstungsposition.');
            }
            $this->assertAsset($assets[0]);
        }
    }

    public function assertReservation(EquipmentReservation $reservation): void
    {
        $this->assertMember($reservation->getMember());
        $this->assertAsset($reservation->getEquipmentItem());
    }
}
