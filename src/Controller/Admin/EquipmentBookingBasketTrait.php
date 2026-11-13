<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\EquipmentBooking;
use App\Entity\EquipmentBookingItem;
use App\Entity\EquipmentCategory;
use App\Entity\EquipmentItem;
use App\Entity\Regulator;
use App\Entity\Tank;
use App\Entity\Member;
use App\Service\EquipmentBookingService;
use App\Service\AdminEquipmentScope;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

trait EquipmentBookingBasketTrait
{
    #[AdminRoute(path: '/equipment-booking-basket', name: 'equipment_booking_basket')]
    public function equipmentBookingBasket(Request $request, EntityManagerInterface $em, EquipmentBookingService $bookingService, AdminUrlGenerator $adminUrlGenerator, AdminEquipmentScope $scope): Response
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');
        $club = $scope->club();
        $items = $em->getRepository(EquipmentItem::class)->findBy(['club' => $club, 'lendable' => true, 'conditionStatus' => 'ready'], ['name' => 'ASC']);
        $regulators = $em->getRepository(Regulator::class)->findBy(['club' => $club, 'lendable' => true, 'conditionStatus' => 'ready'], ['id' => 'ASC']);
        $tanks = $em->getRepository(Tank::class)->findBy(['club' => $club], ['id' => 'ASC']);
        $categories = $em->getRepository(EquipmentCategory::class)->findBy(['club' => $club, 'active' => true], ['name' => 'ASC']);
        $members = $em->getRepository(Member::class)->findOrderedByName($club);
        $errors = [];
        $submitted = [];
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('equipment_booking_basket_'.$club->getId(), (string) $request->request->get('_token', ''))) {
                throw $this->createAccessDeniedException('Ungueltiger CSRF-Token.');
            }
            $submitted = $request->request->all();
            try {
                $memberId = filter_var($submitted['member_id'] ?? null, FILTER_VALIDATE_INT);
                $member = $memberId ? $em->getRepository(Member::class)->find($memberId) : null;
                $startRaw = (string) ($submitted['starts_at'] ?? '');
                $endRaw = (string) ($submitted['ends_at'] ?? '');
                $start = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $startRaw);
                $end = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $endRaw);
                if (!$member instanceof Member || !$member->getClub() || !$start || !$end || $start >= $end) {
                    throw new \DomainException('Mitglied und gueltigen Zeitraum auswaehlen.');
                }
                $scope->assertMember($member);
                $rawSelection = $submitted['asset_ids'] ?? [];
                if (!is_array($rawSelection) || count($rawSelection) < 1 || count($rawSelection) > 100) {
                    throw new \DomainException('Bitte 1 bis 100 Gegenstaende auswaehlen.');
                }
                $booking = (new EquipmentBooking())->setMember($member)->setStartsAt($start)->setEndsAt($end)
                    ->setNotes(trim((string) ($submitted['notes'] ?? '')) ?: null);
                $seen = [];
                foreach ($rawSelection as $value) {
                    if (!is_string($value) || !preg_match('/^(equipment|regulator|tank):([1-9][0-9]*)$/D', $value, $matches)) {
                        throw new \DomainException('Ungueltige Gegenstandsauswahl.');
                    }
                    if (isset($seen[$value])) {
                        throw new \DomainException('Gegenstand mehrfach ausgewaehlt.');
                    }
                    $seen[$value] = true;
                    $type = $matches[1];
                    $id = (int) $matches[2];
                    $class = match ($type) {
                        'equipment' => EquipmentItem::class,
                        'regulator' => Regulator::class,
                        'tank' => Tank::class,
                    };
                    $asset = $em->getRepository($class)->find($id);
                    if (!$asset) {
                        throw new \DomainException('Ein Gegenstand existiert nicht mehr.');
                    }
                    $scope->assertAsset($asset);
                    $line = (new EquipmentBookingItem())->setQuantity(1);
                    match ($type) {
                        'equipment' => $line->setEquipmentItem($asset),
                        'regulator' => $line->setRegulator($asset),
                        'tank' => $line->setTank($asset),
                    };
                    $booking->addItem($line);
                }
                // The booking service must perform the final transactional availability check.
                $bookingService->saveNew($booking);
                $this->addFlash('success', 'Reservierung angelegt.');
                return $this->redirect((clone $adminUrlGenerator)->unsetAll()
                    ->setController(EquipmentBookingCrudController::class)
                    ->setAction(Crud::PAGE_DETAIL)->setEntityId($booking->getId())->generateUrl());
            } catch (\DomainException|\InvalidArgumentException $e) {
                $errors[] = $e->getMessage();
            }
        }
        return $this->render('admin/equipment/booking_basket.html.twig', compact('items', 'regulators', 'tanks', 'categories', 'members', 'errors', 'submitted', 'club'));
    }
}
