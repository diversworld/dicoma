<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Club;
use App\Entity\CompressorFillLog;
use App\Entity\CompressorFillBatch;
use App\Entity\CompressorFillSession;
use App\Entity\EquipmentAssetRecord;
use App\Entity\EquipmentBooking;
use App\Entity\EquipmentBookingItem;
use App\Entity\EquipmentCategory;
use App\Entity\EquipmentDocument;
use App\Entity\EquipmentFillCharge;
use App\Entity\EquipmentItem;
use App\Entity\EquipmentRentalReceipt;
use App\Entity\EquipmentManufacturer;
use App\Entity\EquipmentReservation;
use App\Entity\Member;
use App\Entity\Person;
use App\Entity\Regulator;
use App\Entity\RegulatorModel;
use App\Entity\Tank;
use App\Entity\TankMaintenanceOrder;
use App\Entity\User;
use App\Enum\MembershipStatus;
use App\Service\EquipmentAssetAvailability;
use App\Service\EquipmentBookingLoanService;
use App\Service\EquipmentBookingPositionService;
use App\Service\EquipmentBookingService;
use App\Service\EquipmentBookingWorkflow;
use App\Service\EquipmentCashGuard;
use App\Service\EquipmentMaintenanceService;
use App\Service\EquipmentReservationService;
use App\Service\EquipmentLoanService;
use App\Service\PrivateMemberDocumentStorage;
use App\Service\TankFillingEligibility;
use App\Service\TankMaintenanceService;
use App\Service\TankStatusWorkflow;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\ORM\Tools\SchemaValidator;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class Phase6EquipmentHttpTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private Club $club;
    private User $manager;
    private EquipmentCategory $category;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) { self::markTestSkipped('pdo_sqlite required.'); }
        $_ENV['DATABASE_URL'] = $_SERVER['DATABASE_URL'] = 'sqlite:///:memory:';
        $_ENV['MAILER_DSN'] = $_SERVER['MAILER_DSN'] = 'null://null';
        putenv('DATABASE_URL=sqlite:///:memory:'); putenv('MAILER_DSN=null://null');
        $this->client = static::createClient(['environment' => 'test']);
        $this->client->disableReboot();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        self::assertSame('pdo_sqlite', $this->em->getConnection()->getParams()['driver']);
        (new SchemaTool($this->em))->createSchema($this->em->getMetadataFactory()->getAllMetadata());
        $this->em->getConnection()->executeStatement('CREATE TABLE equipment_cash_correction (id INTEGER PRIMARY KEY AUTOINCREMENT, club_id INT NOT NULL, source_type VARCHAR(20), source_id INT, direction VARCHAR(10), amount_cents INT, reason VARCHAR(500), created_by VARCHAR(180), created_at DATETIME)');
        $this->club = (new Club())->setName('Phase 6 Club');
        $this->em->persist($this->club);
        $this->manager = $this->account('equipment-manager', [User::ROLE_EQUIPMENT_MANAGER, User::ROLE_COMPRESSOR_OPERATOR], $this->club);
        $this->category = (new EquipmentCategory())->setClub($this->club)->setCode('mask')->setName('Masks');
        $this->em->persist($this->category);
        $this->em->flush();
        $this->client->loginUser($this->manager);
        $this->client->request('GET', '/equipment-manager/inventory');
        self::assertResponseIsSuccessful();
    }

    public function testInventoryFormPersistsOwnershipLocationPurchaseInspectionAndApproval(): void
    {
        $item = $this->item('INV-001');
        $crawler = $this->client->request('GET', '/equipment-manager/inventory/equipment/'.$item->getId());
        self::assertResponseIsSuccessful();
        $this->client->submit($crawler->selectButton('Inventarakte speichern')->form(), [
            'form[ownership]' => 'borrowed', 'form[ownerName]' => 'Synthetic Owner', 'form[location]' => 'Store room A',
            'form[purchasedAt]' => '2025-01-31', 'form[purchasePriceCents]' => '125,50',
            'form[inspectionIntervalMonths]' => '12', 'form[nextInspectionAt]' => date('Y-m-d', strtotime('+1 year')),
            'form[approvalRequired]' => '1',
        ]);
        self::assertResponseRedirects();
        $this->em->clear();
        $record = $this->em->getRepository(EquipmentAssetRecord::class)->findOneBy(['equipmentItem' => $item->getId()]);
        self::assertSame('borrowed', $record->getOwnership());
        self::assertSame('Store room A', $record->getLocation());
        self::assertSame(12550, $record->getPurchasePriceCents());
        self::assertTrue($record->isApprovalRequired());
        self::assertSame('2025-01-31', $record->getAsset()->getPurchasedAt()->format('Y-m-d'));
        self::assertSame([], (new SchemaValidator($this->em))->validateMapping());
        $this->client->followRedirect();
        self::assertSelectorTextContains('body', 'inventory_updated');
    }

    public function testApprovalIssuePaymentDamagedReturnRepairReleaseAndStrictClosure(): void
    {
        $item = $this->item('INV-002');
        $record = (new EquipmentAssetRecord($item))->setApprovalRequired(true);
        $this->em->persist($record); $this->em->flush();
        $booking = $this->booking($item);
        self::assertTrue($booking->isApprovalRequired());
        $workflow = static::getContainer()->get(EquipmentBookingWorkflow::class);
        $workflow->approve($booking->getId(), $this->club->getId(), 'manager');
        $loans = static::getContainer()->get(EquipmentBookingLoanService::class);
        $line = $booking->getItems()->first();
        $loans->change($booking->getId(), $line->getId(), 'issue');
        self::assertSame('issued', $booking->getStatus());
        $loans->change($booking->getId(), $line->getId(), 'return', 'damaged', 'Strap damaged during loan');
        self::assertSame('returned', $booking->getStatus());
        self::assertFalse($item->isLendable());
        $order = $this->em->getRepository(TankMaintenanceOrder::class)->findOneBy(['equipmentItem' => $item, 'status' => 'open']);
        self::assertNotNull($order);
        $maintenance = static::getContainer()->get(EquipmentMaintenanceService::class);
        $maintenance->close('equipment', $item->getId(), $this->club->getId(), $order->getId(), 'Strap replaced and tested', 'technician');
        self::assertFalse($item->isLendable());
        $maintenance->release('equipment', $item->getId(), $this->club->getId(), 'Technical check passed after repair', 'technician');
        $this->em->persist(new EquipmentRentalReceipt($booking, 500, 'bank', 'manager', null)); $this->em->flush();
        $workflow->close($booking->getId(), $this->club->getId(), 'manager');
        $this->em->clear();
        self::assertSame('closed', $this->em->find(EquipmentBooking::class, $booking->getId())->getStatus());
        $this->client->request('GET', '/equipment-manager/loans?status=closed');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Abgeschlossen durch manager');
    }

    public function testApprovalIsEnforcedBeforeIssue(): void
    {
        $item = $this->item('INV-APPROVAL');
        $this->em->persist((new EquipmentAssetRecord($item))->setApprovalRequired(true)); $this->em->flush();
        $booking = $this->booking($item);
        $this->expectExceptionMessage('muss zuerst vom Gerätewart genehmigt');
        static::getContainer()->get(EquipmentBookingLoanService::class)->issueAll($booking->getId());
    }

    public function testCancelledBookingReleasesAvailabilityAndLegacyReservationsSeeIssuedBookings(): void
    {
        $item = $this->item('INV-003');
        $booking = $this->booking($item);
        $availability = static::getContainer()->get(EquipmentAssetAvailability::class);
        self::assertFalse($availability->isAvailablePreview($item, $this->club->getId(), $booking->getStartsAt(), $booking->getEndsAt()));
        static::getContainer()->get(EquipmentBookingWorkflow::class)->cancel($booking->getId(), $this->club->getId(), 'manager', 'No longer required');
        self::assertTrue($availability->isAvailablePreview($item, $this->club->getId(), $booking->getStartsAt(), $booking->getEndsAt()));
        $second = $this->booking($item);
        static::getContainer()->get(EquipmentBookingLoanService::class)->issueAll($second->getId());
        $legacy = (new EquipmentReservation())->setEquipmentItem($item)->setMember($this->manager->getMember())
            ->setStartsAt(new \DateTimeImmutable('+10 days'))->setEndsAt(new \DateTimeImmutable('+11 days'));
        self::assertFalse(static::getContainer()->get(EquipmentReservationService::class)->available($legacy));
        self::assertFalse($availability->isAvailablePreview($item, $this->club->getId(), $legacy->getStartsAt(), $legacy->getEndsAt()));
    }

    public function testAppendingPositionUpdatesFrozenPriceAndApproval(): void
    {
        $booking = $this->booking($this->item('INV-004'));
        $second = $this->item('INV-005', '7.50');
        $this->em->persist((new EquipmentAssetRecord($second))->setApprovalRequired(true)); $this->em->flush();
        static::getContainer()->get(EquipmentBookingPositionService::class)->add($booking->getId(), $second->getId());
        self::assertSame(1250, $booking->getAgreedRentalFeeCents());
        self::assertTrue($booking->isApprovalRequired());
        self::assertCount(2, $booking->getItems());
    }

    public function testTankInspectionUsesCalendarDayAndAliasCannotBeDoubleBooked(): void
    {
        $tank = $this->tank('CYL-001');
        $end = new \DateTimeImmutable('+2 days 18:00');
        $tank->setNextCheckDate($end->setTime(0, 0)); $this->em->flush();
        $booking = (new EquipmentBooking())->setMember($this->manager->getMember())->setStartsAt(new \DateTimeImmutable('-1 hour'))->setEndsAt($end);
        $booking->addItem((new EquipmentBookingItem())->setTank($tank));
        static::getContainer()->get(EquipmentBookingService::class)->saveNew($booking);
        static::getContainer()->get(EquipmentBookingLoanService::class)->issueAll($booking->getId());
        self::assertSame('issued', $booking->getStatus());
        $alias = $this->item('CYL-ALIAS'); $alias->setTank($tank); $this->em->flush();
        self::assertFalse(static::getContainer()->get(EquipmentAssetAvailability::class)->isAvailablePreview($alias, $this->club->getId(), new \DateTimeImmutable('+1 day'), $end));
    }

    public function testTechnicalMaintenanceAndExpiredInspectionBlockReservation(): void
    {
        $item = $this->item('INV-006');
        static::getContainer()->get(EquipmentMaintenanceService::class)->open('equipment', $item->getId(), $this->club->getId(), 'defect', 'Broken strap reported', 'Technician', 'manager');
        $availability = static::getContainer()->get(EquipmentAssetAvailability::class);
        self::assertFalse($availability->isAvailablePreview($item, $this->club->getId(), new \DateTimeImmutable(), new \DateTimeImmutable('+1 day')));
        $tank = $this->tank('CYL-EXPIRED');
        $tank->setNextCheckDate(new \DateTimeImmutable('yesterday')); $this->em->flush();
        self::assertFalse($availability->isAvailablePreview($tank, $this->club->getId(), new \DateTimeImmutable(), new \DateTimeImmutable('+1 day')));
    }

    public function testInspectionIntervalsClampMonthEndAndAllInventoryTypesRender(): void
    {
        $item = $this->item('INTERVAL');
        $this->em->persist((new EquipmentAssetRecord($item))->setInspectionIntervalMonths(1)); $this->em->flush();
        $maintenance = static::getContainer()->get(EquipmentMaintenanceService::class);
        $maintenance->inspect('equipment', $item->getId(), $this->club->getId(), '2024-01-31', '', 'Inspection by test technician', 'manager');
        self::assertSame('2024-02-29', $this->em->getRepository(EquipmentAssetRecord::class)->findOneBy(['equipmentItem' => $item])->getNextInspectionAt()->format('Y-m-d'));
        $maintenance->inspect('equipment', $item->getId(), $this->club->getId(), '2025-01-31', '', 'Inspection by test technician', 'manager');
        self::assertSame('2025-02-28', $this->em->getRepository(EquipmentAssetRecord::class)->findOneBy(['equipmentItem' => $item])->getNextInspectionAt()->format('Y-m-d'));
        $manufacturer = (new EquipmentManufacturer())->setClub($this->club)->setName('Synthetic manufacturer');
        $first = (new RegulatorModel())->setClub($this->club)->setManufacturer($manufacturer)->setName('First')->setStage('first');
        $second = (new RegulatorModel())->setClub($this->club)->setManufacturer($manufacturer)->setName('Second')->setStage('second');
        $regulator = (new Regulator())->setClub($this->club)->setInventoryNumber('REG-001')->setManufacturer($manufacturer)
            ->setFirstStageModel($first)->setFirstStageSerial('SER-001')->setPrimarySecondStageModel($second)->setNextServiceAt(new \DateTimeImmutable('+1 year'));
        foreach ([$manufacturer, $first, $second, $regulator] as $entity) { $this->em->persist($entity); }
        $tank = $this->tank('CYL-DETAIL');
        $regulatorId = $regulator->getId();
        $this->client->request('GET', '/equipment-manager/inventory/regulator/'.$regulatorId); self::assertResponseIsSuccessful();
        $this->client->request('GET', '/equipment-manager/inventory/tank/'.$tank->getId()); self::assertResponseIsSuccessful();
    }

    public function testFirstInspectionCreatesExactlyOneAssetRecordWithHistory(): void
    {
        $item = $this->item('FIRST-INSPECTION');
        $performed = new \DateTimeImmutable('today');
        $next = $performed->modify('+1 year');
        static::getContainer()->get(EquipmentMaintenanceService::class)->inspect(
            'equipment', $item->getId(), $this->club->getId(), $performed->format('Y-m-d'), $next->format('Y-m-d'), 'First inspection with test evidence', 'technician',
        );
        self::assertSame(1, $this->em->getRepository(EquipmentAssetRecord::class)->count(['equipmentItem' => $item]));
        $record = $this->em->getRepository(EquipmentAssetRecord::class)->findOneBy(['equipmentItem' => $item]);
        self::assertSame($next->format('Y-m-d'), $record->getNextInspectionAt()->format('Y-m-d'));
        self::assertCount(1, $record->getHistory());
    }

    public function testLinkedCylinderMaintenanceAndReleaseUseOnePhysicalIdentity(): void
    {
        $tank = $this->tank('CYL-LINKED');
        $alias = $this->item('ALIAS-LINKED'); $alias->setTank($tank); $this->em->flush();
        $maintenance = static::getContainer()->get(EquipmentMaintenanceService::class);
        $orderId = $maintenance->open('equipment', $alias->getId(), $this->club->getId(), 'repair', 'Repair valve connection', 'Technician', 'manager');
        self::assertSame($tank, $this->em->find(TankMaintenanceOrder::class, $orderId)->getTank());
        self::assertTrue($maintenance->hasOpenOrder($tank));
        self::assertTrue($maintenance->hasOpenOrder($alias));
        self::assertFalse($alias->isLendable());
        self::assertFalse($tank->isLendable());
        $maintenance->close('tank', $tank->getId(), $this->club->getId(), $orderId, 'Valve repaired and tested', 'technician');
        $maintenance->release('tank', $tank->getId(), $this->club->getId(), 'Valve function verified', 'technician');
        self::assertTrue($alias->isLendable()); self::assertTrue($tank->isLendable());
        static::getContainer()->get(TankFillingEligibility::class)->assertFillable($tank, $this->club->getId());
        $booking = $this->booking($alias);
        static::getContainer()->get(EquipmentBookingLoanService::class)->issueAll($booking->getId());
        $this->expectExceptionMessage('Gegenstand ist noch ausgegeben.');
        $maintenance->release('tank', $tank->getId(), $this->club->getId(), 'Release must not bypass loan', 'technician');
    }

    public function testExistingTankActionsShareMaintenanceHistoryAndPhysicalLoanGuards(): void
    {
        $tank = $this->tank('TANK-OLD-ACTIONS');
        $alias = $this->item('ALIAS-OLD-ACTIONS'); $alias->setTank($tank); $this->em->flush();
        $old = static::getContainer()->get(TankMaintenanceService::class);
        $order = $old->open($tank->getId(), 'Valve needs scheduled repair', 'technician', $this->club->getId());
        self::assertFalse($alias->isLendable());
        $old->closeForTank($tank->getId(), $order, 'Valve serviced and verified', 'technician', $this->club->getId());
        $workflow = static::getContainer()->get(TankStatusWorkflow::class);
        $workflow->apply($tank->getId(), 'release', 'Technical check completed', 'technician', $this->club->getId());
        self::assertTrue($alias->isLendable());
        self::assertCount(3, $this->em->getRepository(EquipmentAssetRecord::class)->findOneBy(['tank' => $tank])->getHistory());
        $booking = $this->booking($alias);
        static::getContainer()->get(EquipmentBookingLoanService::class)->issueAll($booking->getId());
        $this->expectExceptionMessage('Gegenstand ist noch ausgegeben.');
        $workflow->apply($tank->getId(), 'release', 'Must not release a loaned tank', 'technician', $this->club->getId());
    }

    public function testReadyReturnCannotCloseWithOpenMaintenance(): void
    {
        $item = $this->item('READY-OPEN-ORDER', '0.00');
        $booking = $this->booking($item);
        $loans = static::getContainer()->get(EquipmentBookingLoanService::class);
        $loans->issueAll($booking->getId());
        $loans->change($booking->getId(), $booking->getItems()->first()->getId(), 'return', 'ready');
        $this->em->persist(new TankMaintenanceOrder($item, 'Unresolved maintenance despite ready return')); $this->em->flush();
        $this->expectExceptionMessage('Schadens-/Wartungsbearbeitung zuerst abschließen');
        static::getContainer()->get(EquipmentBookingWorkflow::class)->close($booking->getId(), $this->club->getId(), 'manager');
    }

    public function testAdjacentReservationsAreAllowedAndOverlapsRejected(): void
    {
        $item = $this->item('ADJACENT');
        $first = $this->booking($item);
        $next = (new EquipmentBooking())->setMember($this->manager->getMember())->setStartsAt($first->getEndsAt())->setEndsAt($first->getEndsAt()->modify('+1 day'));
        $next->addItem((new EquipmentBookingItem())->setEquipmentItem($item));
        static::getContainer()->get(EquipmentBookingService::class)->saveNew($next);
        self::assertNotNull($next->getId());
        $overlap = (new EquipmentBooking())->setMember($this->manager->getMember())->setStartsAt($first->getEndsAt()->modify('-1 second'))->setEndsAt($next->getEndsAt());
        $overlap->addItem((new EquipmentBookingItem())->setEquipmentItem($item));
        $this->expectExceptionMessage('bereits reserviert oder noch ausgegeben');
        static::getContainer()->get(EquipmentBookingService::class)->saveNew($overlap);
    }

    public function testLegacyReturnedNotesSurviveReloadAndCreateMaintenance(): void
    {
        $item = $this->item('LEGACY-RETURN');
        $reservation = (new EquipmentReservation())->setMember($this->manager->getMember())->setEquipmentItem($item)
            ->setStartsAt(new \DateTimeImmutable('-1 hour'))->setEndsAt(new \DateTimeImmutable('+1 day'))->setStatus('issued');
        $this->em->persist($reservation); $this->em->flush();
        static::getContainer()->get(EquipmentLoanService::class)->returnItem($reservation->getId(), 'damaged', 'Damaged strap after use');
        $this->em->clear();
        self::assertSame('Damaged strap after use', $this->em->find(EquipmentReservation::class, $reservation->getId())->getReturnNotes());
        self::assertSame(1, $this->em->getRepository(TankMaintenanceOrder::class)->count(['status' => 'open']));
    }

    public function testRentalPaymentRejectsWrongAmountBeforeAcceptingExactFee(): void
    {
        $booking = $this->booking($this->item('PAYMENT-EXACT'));
        static::getContainer()->get(EquipmentBookingLoanService::class)->issueAll($booking->getId());
        $crawler = $this->client->request('GET', '/equipment-manager/loans?checkout='.$booking->getId());
        $form = $crawler->filter('form[action$="/collect-fee"]')->form();
        $this->client->submit($form, ['amount' => '1,00', 'method' => 'cash']); self::assertResponseRedirects();
        self::assertSame(0, $this->em->getRepository(EquipmentRentalReceipt::class)->count([]));
        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('body', 'festgeschriebenen Leihgebühr');
        $this->client->submit($crawler->filter('form[action$="/collect-fee"]')->form(), ['amount' => '5,00', 'method' => 'bank']);
        self::assertResponseRedirects();
        self::assertSame(500, $this->em->getRepository(EquipmentRentalReceipt::class)->findOneBy([])->getAmountCents());
    }

    public function testDocumentUploadAndForeignClubIsolation(): void
    {
        $item = $this->item('INV-DOC');
        $url = '/equipment-manager/inventory/equipment/'.$item->getId();
        $crawler = $this->client->request('GET', $url);
        $form = $crawler->selectButton('Dokument ablegen')->form();
        $temp = tempnam(sys_get_temp_dir(), 'phase6-doc-'); file_put_contents($temp, "%PDF-1.4\nSynthetic inventory evidence\n%%EOF");
        $stored = null;
        try {
            $this->client->request('POST', $url.'/documents', ['_token' => $form['_token']->getValue()], ['document' => new UploadedFile($temp, 'evidence.pdf', 'application/pdf', null, true)]);
            self::assertResponseRedirects();
            $document = $this->em->getRepository(EquipmentDocument::class)->findOneBy([]);
            self::assertNotNull($document);
            $stored = static::getContainer()->get(PrivateMemberDocumentStorage::class)->path($document->getStorageName());
            $this->client->request('GET', '/equipment-manager/inventory/documents/'.$document->getId());
            self::assertResponseIsSuccessful();
            $otherClub = (new Club())->setName('Other club'); $this->em->persist($otherClub);
            $other = $this->account('other-manager', [User::ROLE_EQUIPMENT_MANAGER], $otherClub);
            $this->client->loginUser($other);
            $this->client->request('GET', $url); self::assertResponseStatusCodeSame(404);
            $this->client->request('GET', '/equipment-manager/inventory/documents/'.$document->getId()); self::assertResponseStatusCodeSame(404);
        } finally {
            if (is_file($temp)) { unlink($temp); }
            if ($stored !== null && is_file($stored)) { unlink($stored); }
        }
    }

    public function testFillingChargePaymentCashJournalExportAndClosingUseSameAmount(): void
    {
        $tank = $this->tank('CYL-FILL');
        $crawler = $this->client->request('GET', '/equipment-manager/compressor-log');
        $this->client->submit($crawler->selectButton('Füllung verbindlich speichern')->form(), [
            'cylinder' => 'CYL-FILL', 'tank_id' => (string) $tank->getId(), 'compressor' => 'Test Compressor',
            'start_bar' => '40', 'end_bar' => '200', 'fee' => '3,50',
            'cylinder_check' => '1', 'air_quality_check' => '1', 'filter_check' => '1',
        ]);
        self::assertResponseRedirects();
        self::assertSame(1, $this->em->getRepository(CompressorFillLog::class)->count([]));
        $charge = $this->em->getRepository(EquipmentFillCharge::class)->findOneBy([]);
        self::assertSame(350, $charge->getAmountCents());
        $crawler = $this->client->request('GET', '/equipment-manager/fill-billing');
        $this->client->submit($crawler->selectButton('Zahlung bestätigen')->form(), ['method' => 'cash']);
        self::assertResponseRedirects();
        self::assertSame(350, static::getContainer()->get(EquipmentCashGuard::class)->availableCents($this->club->getId()));
        foreach (['/equipment-manager/cashbook', '/equipment-manager/cashbook/day', '/equipment-manager/cashbook/day.csv', '/equipment-manager/cashbook/closing'] as $url) {
            $this->client->request('GET', $url); self::assertResponseIsSuccessful();
            if ($url === '/equipment-manager/cashbook/day.csv') {
                $csv = $this->client->getInternalResponse()->getContent();
                self::assertSame(1, substr_count($csv, 'Füllgebühr'));
                self::assertStringContainsString('"Gesamt EUR";3,50', $csv);
            } elseif ($url === '/equipment-manager/cashbook/closing') {
                self::assertSelectorTextContains('body', 'Bewegungen: 1');
                self::assertSelectorTextContains('body', 'Bareinnahmen: 3,50');
            } else {
                self::assertSelectorTextContains('.card', '3,50');
                self::assertSame(1, $this->client->getCrawler()->filter('tbody tr')->reduce(static fn ($row): bool => str_contains($row->text(), 'Füllgebühr'))->count());
            }
        }
        $charge = $this->em->find(EquipmentFillCharge::class, $charge->getId());
        self::assertNotNull($charge->getCollectedAt());
        $crawler = $this->client->request('GET', '/equipment-manager/cashbook/corrections');
        $this->client->submit($crawler->selectButton('Bargeldkorrektur verbindlich buchen')->form(), ['kind' => 'fill', 'reference_id' => $charge->getId(), 'reason' => 'Refund after duplicate request']);
        self::assertResponseRedirects();
        self::assertSame(0, static::getContainer()->get(EquipmentCashGuard::class)->availableCents($this->club->getId()));
        $this->client->request('GET', '/equipment-manager/cashbook/day');
        self::assertSelectorTextContains('body', 'Netto-Barbewegung: 0,00');
    }

    public function testGroupFillAndSessionClosureRecordFeeAndOperatingHours(): void
    {
        $tank = $this->tank('CYL-BATCH');
        $session = new CompressorFillSession($this->club, $this->manager, 'Compressor batch', 100, null);
        $this->em->persist($session); $this->em->flush();
        $crawler = $this->client->request('GET', '/equipment-manager/compressor-evenings?session='.$session->getId());
        $this->client->submit($crawler->filter('form')->reduce(static fn ($node): bool => $node->filter('input[name="action"][value="batch"]')->count() > 0)->form(), [
            'tank_1' => (string) $tank->getId(), 'pressure' => '200', 'fee' => '2,50', 'cylinder_check' => '1',
        ]);
        self::assertResponseRedirects();
        self::assertSame(1, $this->em->getRepository(CompressorFillBatch::class)->count([]));
        self::assertSame(250, $this->em->getRepository(EquipmentFillCharge::class)->findOneBy([])->getAmountCents());
        $crawler = $this->client->followRedirect();
        $this->client->submit($crawler->selectButton('Füllabend abschließen')->form(), ['closing_hours' => '10,5']);
        self::assertResponseRedirects();
        $fresh = $this->em->find(CompressorFillSession::class, $session->getId());
        self::assertSame(105, $fresh->getClosingHoursTenths());
        self::assertNotNull($fresh->getClosedAt());
    }

    public function testNonOperatorCannotRecordFillAndGetCannotMutateWorkflow(): void
    {
        $manager = $this->account('not-an-operator', [User::ROLE_EQUIPMENT_MANAGER], $this->club);
        $this->client->loginUser($manager);
        $crawler = $this->client->request('GET', '/equipment-manager/compressor-log');
        $this->client->submit($crawler->selectButton('Füllung verbindlich speichern')->form());
        self::assertResponseStatusCodeSame(403);
        self::assertSame(0, $this->em->getRepository(CompressorFillLog::class)->count([]));
        $this->client->request('GET', '/equipment-manager/loans/1/approve');
        self::assertResponseStatusCodeSame(405);
    }

    #[DataProvider('unsafeFills')]
    public function testBothFillingSurfacesRejectUnsafeTanks(string $surface, string $state): void
    {
        $tank = $this->tank('UNSAFE-FILL');
        if ($state === 'expired') { $tank->setNextCheckDate(new \DateTimeImmutable('yesterday')); }
        elseif ($state === 'missing') { $tank->setNextCheckDate(null); }
        elseif ($state === 'damaged') { $tank->setConditionStatus('damaged'); }
        else {
            $alias = $this->item('UNSAFE-ALIAS'); $alias->setTank($tank);
            $this->em->persist(new TankMaintenanceOrder($alias, 'Legacy maintenance order without status mirror'));
        }
        $session = new CompressorFillSession($this->club, $this->manager, 'Test compressor', 100, null);
        $this->em->persist($session); $this->em->flush();
        $url = $surface === 'single' ? '/equipment-manager/compressor-log' : '/equipment-manager/compressor-evenings?session='.$session->getId();
        $crawler = $this->client->request('GET', $url);
        if ($surface === 'single') {
            $this->client->submit($crawler->selectButton('Füllung verbindlich speichern')->form(), [
                'cylinder' => 'UNSAFE-FILL', 'tank_id' => (string) $tank->getId(), 'compressor' => 'Test compressor',
                'start_bar' => '40', 'end_bar' => '200', 'fee' => '2,50', 'cylinder_check' => '1', 'air_quality_check' => '1', 'filter_check' => '1',
            ]);
        } else {
            $this->client->submit($crawler->filter('form')->reduce(static fn ($node): bool => $node->filter('input[name="action"][value="batch"]')->count() > 0)->form(), [
                'tank_1' => (string) $tank->getId(), 'pressure' => '200', 'fee' => '2,50', 'cylinder_check' => '1',
            ]);
        }
        self::assertResponseStatusCodeSame($surface === 'group' && $state !== 'order' ? 200 : 303);
        self::assertSame(0, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM equipment_fill_charge'));
        self::assertSame(0, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM compressor_fill_log'));
        self::assertSame(0, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM compressor_fill_batch'));
    }

    public static function unsafeFills(): iterable
    {
        foreach (['single', 'group'] as $surface) {
            foreach (['expired', 'missing', 'damaged', 'order'] as $state) { yield $surface.' '.$state => [$surface, $state]; }
        }
    }

    public function testInventoryMaintenanceAndUploadRejectMissingCsrf(): void
    {
        $item = $this->item('CSRF');
        $url = '/equipment-manager/inventory/equipment/'.$item->getId();
        $this->client->request('POST', $url.'/maintenance', ['operation' => 'release', 'reason' => 'Missing CSRF token']);
        self::assertResponseStatusCodeSame(403);
        $this->client->request('POST', $url.'/documents'); self::assertResponseStatusCodeSame(403);
        self::assertSame(0, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM equipment_document'));
    }

    private function item(string $inventory, string $fee = '5.00'): EquipmentItem
    {
        $item = (new EquipmentItem())->setClub($this->club)->setCategory($this->category)->setInventoryNumber($inventory)->setName('Test mask')->setRentalFee($fee);
        $this->em->persist($item); $this->em->flush();
        return $item;
    }

    private function tank(string $inventory): Tank
    {
        $tank = (new Tank())->setClub($this->club)->setInventory($inventory)->setSize(12)->setSerialnumber($inventory)
            ->setLastCheckDate(new \DateTimeImmutable('-1 year'))->setNextCheckDate(new \DateTimeImmutable('+1 year'));
        $this->em->persist($tank); $this->em->flush();
        return $tank;
    }

    private function booking(EquipmentItem $item): EquipmentBooking
    {
        $booking = (new EquipmentBooking())->setMember($this->manager->getMember())->setStartsAt(new \DateTimeImmutable('-1 hour'))->setEndsAt(new \DateTimeImmutable('+2 days'));
        $booking->addItem((new EquipmentBookingItem())->setEquipmentItem($item));
        static::getContainer()->get(EquipmentBookingService::class)->saveNew($booking);
        return $booking;
    }

    private function account(string $name, array $roles, Club $club): User
    {
        $person = (new Person())->setFirstname($name)->setLastname('Synthetic')->setEmail($name.'@example.invalid');
        $member = (new Member())->setClub($club)->setPerson($person)->setMembershipStatus(MembershipStatus::ACTIVE);
        $user = (new User())->setUser($name)->setEmail($name.'@example.invalid')->setPassword('unused')->setIsVerified(true)->setMember($member)->setRoles($roles);
        $this->em->persist($user); $this->em->flush();
        return $user;
    }
}
