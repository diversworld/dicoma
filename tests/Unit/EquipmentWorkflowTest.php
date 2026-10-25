<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\Club;
use App\Entity\CompressorFillLog;
use App\Entity\EquipmentAssetRecord;
use App\Entity\EquipmentBooking;
use App\Entity\EquipmentBookingItem;
use App\Entity\EquipmentFillCharge;
use App\Entity\EquipmentItem;
use App\Entity\EquipmentReservation;
use App\Entity\Member;
use App\Entity\Tank;
use App\Entity\TankMaintenanceOrder;
use App\Entity\User;
use App\Service\EquipmentAssetAvailability;
use App\Service\EquipmentBookingWorkflow;
use App\Service\EquipmentInventory;
use App\Service\EquipmentMaintenanceService;
use App\Service\EquipmentRentalEligibility;
use App\Service\TankFillingEligibility;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EquipmentWorkflowTest extends TestCase
{
    public function testLegacyReturnNotesUseTheMappedProperty(): void
    {
        $reservation = (new EquipmentReservation())->setReturnNotes('Leak found during return');
        self::assertSame('Leak found during return', $reservation->getReturnNotes());
        self::assertSame('Leak found during return', (new \ReflectionProperty($reservation, 'returnedNotes'))->getValue($reservation));
    }

    public function testAssetRecordsPreservePurchaseDatesAndPhysicalCylinderIdentity(): void
    {
        $tank = (new Tank())->setBuyDate(new \DateTimeImmutable('2024-01-31'));
        $item = (new EquipmentItem())->setTank($tank);
        self::assertSame($tank, EquipmentInventory::physicalAsset($item));
        $record = new EquipmentAssetRecord($tank);
        self::assertSame('2024-01-31', $record->getPurchasedAt()->format('Y-m-d'));
        $record->record('maintenance_opened', 'operator', 'Test maintenance');
        self::assertSame('operator', $record->getHistory()[0]['actor']);
        self::assertSame('maintenance_opened', $record->getHistory()[0]['event']);
    }

    public function testApprovalCanBeInvalidatedWhenAnotherApprovalRequiredAssetIsAdded(): void
    {
        $booking = new EquipmentBooking();
        $booking->requireApproval();
        $booking->approve('manager');
        self::assertNotNull($booking->getApprovedAt());
        self::assertSame('manager', $booking->getApprovedBy());
        $booking->requireApproval();
        self::assertTrue($booking->isApprovalRequired());
        self::assertNull($booking->getApprovedAt());
        self::assertNull($booking->getApprovedBy());
    }

    public function testMaintenanceOrdersCoverAllInventoryTypesAndDoNotReleaseOnClosure(): void
    {
        foreach ([new EquipmentItem(), new \App\Entity\Regulator(), new Tank()] as $asset) {
            $asset->setConditionStatus('damaged')->setLendable(false);
            $order = new TankMaintenanceOrder($asset, 'Broken strap needs replacement', 'defect', 'Technician', 'Manager');
            self::assertSame($asset, $order->getAsset());
            self::assertSame('defect', $order->getKind());
            self::assertSame('Technician', $order->getResponsible());
            self::assertSame('Manager', $order->getOpenedBy());
            $order->close('Technician', 'Replaced strap and tested fixture');
            self::assertSame('closed', $order->getStatus());
            self::assertSame('damaged', $asset->getConditionStatus());
            self::assertFalse($asset->isLendable());
        }
    }

    public function testClosureRequiresReturnedItemsAndAllowsExplicitlyFreeLoans(): void
    {
        [$booking, $club] = $this->returnedBooking();
        $this->workflow($booking)->close(1, $club->getId(), 'manager');
        self::assertSame('closed', $booking->getStatus());
        self::assertSame('manager', $booking->getClosedBy());
        self::assertNotNull($booking->getClosedAt());
    }

    public function testUnpaidLoanCannotBeClosed(): void
    {
        [$booking, $club] = $this->returnedBooking();
        $booking->setAgreedRentalFeeCents(500);
        $this->expectExceptionMessage('Leihgebühr ist noch nicht vollständig bezahlt.');
        $this->workflow($booking)->close(1, $club->getId(), 'manager');
    }

    public function testDamagedReturnCannotBeClosedBeforeTechnicalRelease(): void
    {
        [$booking, $club, $asset] = $this->returnedBooking('damaged');
        $asset->setConditionStatus('damaged')->setLendable(false);
        $this->expectExceptionMessage('Schadens-/Wartungsbearbeitung zuerst abschließen');
        $this->workflow($booking)->close(1, $club->getId(), 'manager');
    }

    public function testRepairedAndReleasedReturnCanBeClosed(): void
    {
        [$booking, $club] = $this->returnedBooking('damaged');
        $this->workflow($booking)->close(1, $club->getId(), 'manager');
        self::assertSame('closed', $booking->getStatus());
    }

    public function testForeignClubCannotCloseBooking(): void
    {
        [$booking] = $this->returnedBooking();
        $this->expectExceptionMessage('Buchung nicht im eigenen Verein.');
        $this->workflow($booking)->close(1, 99, 'other manager');
    }

    public function testUnknownLegacyPriceCannotBeSilentlyTreatedAsFree(): void
    {
        [$booking, $club] = $this->returnedBooking();
        $booking->setAgreedRentalFeeCents(null);
        $this->expectExceptionMessage('Vereinbarte Leihgebühr fehlt.');
        $this->workflow($booking)->close(1, $club->getId(), 'manager');
    }

    public function testLegacyPriceConfirmationUsesExactCents(): void
    {
        [$booking, $club] = $this->returnedBooking();
        $booking->setAgreedRentalFeeCents(null);
        $this->workflow($booking)->confirmLegacyFee(1, $club->getId(), '12,34', 'manager');
        self::assertSame(1234, $booking->getAgreedRentalFeeCents());
        self::assertStringContainsString('manager: 12.34 EUR', $booking->getNotes());
    }

    #[DataProvider('invalidFillingStates')]
    public function testFillingRejectsUnsafeCylinders(string $condition, ?string $due, int $clubId): void
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $repo = $this->createStub(EntityRepository::class);
        $repo->method('findOneBy')->willReturn(null);
        $em->method('getRepository')->willReturn($repo);
        $inventory = new EquipmentInventory($em);
        $maintenance = new EquipmentMaintenanceService($em, $inventory);
        $club = new Club(); self::id($club, 1);
        $tank = (new Tank())->setClub($club)->setConditionStatus($condition);
        $tank->setNextCheckDate($due ? new \DateTimeImmutable($due) : null);
        $this->expectException(\DomainException::class);
        (new TankFillingEligibility($maintenance))->assertFillable($tank, $clubId);
    }

    public static function invalidFillingStates(): iterable
    {
        yield 'damaged' => ['damaged', '+1 year', 1];
        yield 'maintenance' => ['maintenance', '+1 year', 1];
        yield 'missing inspection' => ['ready', null, 1];
        yield 'expired inspection' => ['ready', 'yesterday', 1];
        yield 'foreign club' => ['ready', '+1 year', 2];
    }

    public function testFillPaymentsAreExplicitAndCannotBeCollectedTwice(): void
    {
        $club = new Club(); $user = new User();
        $log = new CompressorFillLog($club, $user, 'Cylinder A', 'Compressor A', 40, 200);
        $charge = new EquipmentFillCharge($club, $log, 1234);
        self::assertNull($charge->getCollectedAt());
        $charge->collect('bank', 'manager');
        self::assertSame(1234, $charge->getAmountCents());
        self::assertSame('bank', $charge->getMethod());
        self::assertSame('manager', $charge->getCollectedBy());
        $this->expectException(\DomainException::class);
        $charge->collect('cash', 'other');
    }

    /** @return array{EquipmentBooking,Club,EquipmentItem} */
    private function returnedBooking(string $condition = 'ready'): array
    {
        $club = new Club(); self::id($club, 1);
        $member = (new Member())->setClub($club);
        $asset = (new EquipmentItem())->setClub($club);
        $booking = (new EquipmentBooking())->setMember($member)->setAgreedRentalFeeCents(0)->setStatus('returned');
        self::id($booking, 1);
        $line = (new EquipmentBookingItem())->setEquipmentItem($asset);
        $line->markIssued(new \DateTimeImmutable('-1 hour'));
        $line->markReturned(new \DateTimeImmutable(), $condition, 'Return inspection');
        $booking->addItem($line);
        return [$booking, $club, $asset];
    }

    private function workflow(EquipmentBooking $booking): EquipmentBookingWorkflow
    {
        $em = $this->createStub(\Doctrine\ORM\EntityManager::class);
        $em->method('find')->willReturn($booking);
        $em->method('getReference')->willReturn($booking->getMember()->getClub());
        $em->method('wrapInTransaction')->willReturnCallback(static fn (callable $work): mixed => $work());
        $repo = $this->createStub(EntityRepository::class);
        $repo->method('findOneBy')->willReturn(null);
        $em->method('getRepository')->willReturn($repo);
        $inventory = new EquipmentInventory($em);
        $maintenance = new EquipmentMaintenanceService($em, $inventory);
        $availability = new EquipmentAssetAvailability($em, $inventory, $maintenance, new EquipmentRentalEligibility());
        return new EquipmentBookingWorkflow($em, $availability, $maintenance);
    }

    private static function id(object $entity, int $id): void
    {
        (new \ReflectionProperty($entity, 'id'))->setValue($entity, $id);
    }
}
