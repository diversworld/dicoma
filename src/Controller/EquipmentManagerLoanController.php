<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\EquipmentBooking;
use App\Entity\EquipmentBookingItem;
use App\Entity\EquipmentRentalReceipt;
use Doctrine\DBAL\LockMode;
use App\Entity\User;
use App\Service\EquipmentBookingLoanService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_EQUIPMENT_MANAGER')]
final class EquipmentManagerLoanController extends AbstractController
{
    private function clubId(): int
    {
        $user = $this->getUser();
        $clubId = $user instanceof User ? $user->getMember()?->getClub()?->getId() : null;
        if ($clubId === null) {
            throw $this->createAccessDeniedException('Kein Verein zugeordnet.');
        }
        return $clubId;
    }

    #[Route('/equipment-manager/loans', name: 'equipment_manager_loans', methods: ['GET'])]
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        $clubId = $this->clubId();
        $filter = $request->query->get('status', 'open');
        if (!is_string($filter) || !in_array($filter, ['open', 'all', 'returned', 'cancelled', 'closed'], true)) {
            throw $this->createNotFoundException('Unbekannter Filter.');
        }

        $qb = $em->getRepository(EquipmentBooking::class)->createQueryBuilder('b')
            ->join('b.member', 'm')->join('m.club', 'c')
            ->leftJoin('b.items', 'bi')->addSelect('bi')
            ->leftJoin('bi.equipmentItem', 'ei')->addSelect('ei')
            ->leftJoin('bi.regulator', 'reg')->addSelect('reg')
            ->leftJoin('bi.tank', 'tank')->addSelect('tank')
            ->where('c.id = :clubId')
            ->setParameter('clubId', $clubId)
            ->orderBy('b.createdAt', 'DESC');
        if ($filter === 'open') {
            $qb->andWhere('b.status IN (:statuses)')->setParameter('statuses', ['reserved', 'partially_issued', 'issued', 'partially_returned', 'returned']);
        } elseif ($filter !== 'all') {
            $qb->andWhere('b.status = :status')->setParameter('status', $filter);
        }
        // Buchungen zuerst begrenzen, um durch Collection-Joins keine unvollständigen Positionen zu erhalten.
        $idsQb = $em->getRepository(EquipmentBooking::class)->createQueryBuilder('b')
            ->select('b.id')->join('b.member', 'm')->join('m.club', 'c')
            ->where('c.id = :clubId')->setParameter('clubId', $clubId)
            ->orderBy('b.createdAt', 'DESC')->setMaxResults(100);
        if ($filter === 'open') {
            $idsQb->andWhere('b.status IN (:statuses)')->setParameter('statuses', ['reserved', 'partially_issued', 'issued', 'partially_returned', 'returned']);
        } elseif ($filter !== 'all') {
            $idsQb->andWhere('b.status = :status')->setParameter('status', $filter);
        }
        // DICOMA_CHECKOUT_WORKSPACE_V1: fetch a specific club booking, even if older than 100.
        $directCheckout = $request->query->get('checkout');
        if ($directCheckout !== null) {
            if (!is_string($directCheckout) || !preg_match('/^[1-9][0-9]*$/D', $directCheckout)) {
                throw $this->createNotFoundException('Invalid booking.');
            }
            $idsQb->andWhere('b.id = :checkoutId')->setParameter('checkoutId', (int) $directCheckout)->setMaxResults(1);
        }
        $ids = array_column($idsQb->getQuery()->getArrayResult(), 'id');
        $bookings = [];
        if ($ids !== []) {
            $bookings = $qb->andWhere('b.id IN (:ids)')->setParameter('ids', $ids)->getQuery()->getResult();
        }
        if ($directCheckout !== null && $bookings === []) {
            throw $this->createNotFoundException('Booking not found.');
        }
        // Eine Zahlung gilt fuer die gesamte Ausleihbuchung, nicht fuer einzelne Gegenstaende.
        // Alle Zahlungsbelege in einer Query laden (kein N+1 pro Buchung).
        $rentalReceipts = [];
        if ($bookings !== []) {
            $receipts = $em->getRepository(EquipmentRentalReceipt::class)->createQueryBuilder('r')
                ->join('r.booking', 'b')
                ->where('b.id IN (:bookingIds)')
                ->setParameter('bookingIds', array_map(static fn (EquipmentBooking $booking): int => $booking->getId(), $bookings))
                ->getQuery()->getResult();
            foreach ($receipts as $receipt) {
                $rentalReceipts[$receipt->getBooking()->getId()] = $receipt;
            }
        }
        // DICOMA_RENTAL_FEE_PREVIEW_V1
        // Geldbeträge ausschließlich in Cent addieren; Flaschen besitzen hier keinen hinterlegten Leihpreis.
        $rentalFeePreview = [];
        $rentalFeeMissing = [];
        foreach ($bookings as $booking) {
            $cents = 0;
            $missing = false;
            foreach ($booking->getItems() as $line) {
                $asset = $line->getEquipmentItem() ?? $line->getRegulator() ?? $line->getTank();
                if ($asset !== null) {
                    $fee = $asset->getRentalFee();
                    if (!preg_match('/^\d{1,8}\.\d{2}$/D', $fee)) {
                        $missing = true;
                        continue;
                    }
                    [$euros, $centPart] = explode('.', $fee);
                    $cents += (int) $euros * 100 + (int) $centPart;
                }
            }
            // DICOMA_USE_AGREED_FEE_V1: historical bookings remain dynamic.
            if ($booking->getAgreedRentalFeeCents() !== null) {
                $cents = $booking->getAgreedRentalFeeCents();
                $missing = false;
            }
            $rentalFeePreview[$booking->getId()] = $cents;
            $rentalFeeMissing[$booking->getId()] = $missing;
        }
        return $this->render('equipment_manager/loans.html.twig', [
            'bookings' => $bookings, 'filter' => $filter, 'rentalReceipts' => $rentalReceipts,
            'rentalFeePreview' => $rentalFeePreview, 'rentalFeeMissing' => $rentalFeeMissing,
        ]);
    }

    #[Route('/equipment-manager/loans/{bookingId}/cancel', name: 'equipment_manager_loan_cancel', methods: ['POST'], requirements: ['bookingId' => '\d+'])]
    public function cancel(int $bookingId, Request $request, EntityManagerInterface $em): Response
    {
        $clubId = $this->clubId();
        if (!$this->isCsrfTokenValid('loan_cancel_'.$bookingId, (string) $request->request->get('_token', ''))) {
            throw $this->createAccessDeniedException('Ungültiges CSRF-Token.');
        }
        $reason = trim((string) $request->request->get('reason', ''));
        if (mb_strlen($reason) < 5 || mb_strlen($reason) > 500) {
            $this->addFlash('danger', 'Bitte einen Stornogrund mit 5 bis 500 Zeichen eingeben.');
            return $this->redirectToRoute('equipment_manager_loans', [], Response::HTTP_SEE_OTHER);
        }
        try {
            $em->wrapInTransaction(function () use ($em, $bookingId, $clubId, $reason): void {
                $booking = $em->find(EquipmentBooking::class, $bookingId, LockMode::PESSIMISTIC_WRITE);
                if (!$booking || $booking->getMember()?->getClub()?->getId() !== $clubId) {
                    throw new \DomainException('Buchung nicht gefunden oder anderer Verein.');
                }
                if ($booking->getStatus() !== 'reserved') {
                    throw new \DomainException('Nur vollständig reservierte Buchungen können storniert werden.');
                }
                foreach ($booking->getItems() as $line) {
                    if ($line->getLoanStatus() !== 'reserved') {
                        throw new \DomainException('Mindestens ein Gegenstand wurde bereits ausgegeben.');
                    }
                }
                if ($em->getRepository(EquipmentRentalReceipt::class)->findOneBy(['booking' => $booking])) {
                    throw new \DomainException('Es ist bereits eine Zahlung erfasst. Bitte zunächst die Rückzahlung über die Kassenkorrektur klären.');
                }
                $booking->setStatus('cancelled');
                $booking->setNotes(trim(($booking->getNotes() ? $booking->getNotes()."\n" : '').'Storniert am '.(new \DateTimeImmutable())->format('d.m.Y H:i').' durch Gerätewart: '.$reason));
                $em->flush();
            });
            $this->addFlash('success', 'Reservierung #'.$bookingId.' wurde storniert.');
        } catch (\DomainException $e) {
            $this->addFlash('danger', $e->getMessage());
        }
        return $this->redirectToRoute('equipment_manager_loans', [], Response::HTTP_SEE_OTHER);
    }

    // DICOMA_BATCH_ISSUE_V1
    #[Route('/equipment-manager/loans/{bookingId}/issue-all', name: 'equipment_manager_loan_issue_all', methods: ['POST'], requirements: ['bookingId' => '\\d+'])]
    public function issueAll(int $bookingId, Request $request, EntityManagerInterface $em, EquipmentBookingLoanService $service): Response
    {
        if (!$this->isCsrfTokenValid('loan_issue_all_'.$bookingId, (string) $request->request->get('_token', ''))) {
            throw $this->createAccessDeniedException('Ungueltiges CSRF-Token.');
        }
        $booking = $em->find(EquipmentBooking::class, $bookingId);
        if (!$booking || $booking->getMember()?->getClub()?->getId() !== $this->clubId()) {
            throw $this->createNotFoundException('Buchung nicht gefunden.');
        }
        try {
            $count = $service->issueAll($bookingId);
            $this->addFlash('success', $count.' Gegenstaende ausgegeben.');
        } catch (\DomainException $e) {
            $this->addFlash('danger', $e->getMessage().' Es wurde kein weiterer Gegenstand ausgegeben.');
        }
        return $this->redirectToRoute('equipment_manager_loans', ['checkout' => $bookingId], Response::HTTP_SEE_OTHER);
    }

    #[Route('/equipment-manager/loans/{bookingId}/items/{lineId}/{operation}', name: 'equipment_manager_loan_action', methods: ['POST'], requirements: ['bookingId' => '\d+', 'lineId' => '\d+', 'operation' => 'issue|return'])]
    public function action(
        int $bookingId,
        int $lineId,
        string $operation,
        Request $request,
        EntityManagerInterface $em,
        EquipmentBookingLoanService $service,
    ): Response {
        $clubId = $this->clubId();
        if (!$this->isCsrfTokenValid('loan_'.$bookingId.'_'.$lineId.'_'.$operation, (string) $request->request->get('_token', ''))) {
            throw $this->createAccessDeniedException('Ungültiges CSRF-Token.');
        }
        $booking = $em->getRepository(EquipmentBooking::class)->find($bookingId);
        $line = $em->getRepository(EquipmentBookingItem::class)->find($lineId);
        if (!$booking || !$line || $line->getBooking()?->getId() !== $bookingId || $booking->getMember()?->getClub()?->getId() !== $clubId) {
            throw $this->createNotFoundException('Buchung oder Position nicht gefunden.');
        }
        $asset = $line->getEquipmentItem() ?? $line->getRegulator() ?? $line->getTank();
        if (!$asset || $asset->getClub()?->getId() !== $clubId) {
            throw $this->createAccessDeniedException('Gegenstand gehört nicht zu deinem Verein.');
        }
        $condition = null;
        $notes = null;
        if ($operation === 'return') {
            $condition = $request->request->get('condition');
            $notes = trim((string) $request->request->get('notes', ''));
            if (!is_string($condition) || !in_array($condition, ['ready', 'maintenance', 'damaged'], true) || mb_strlen($notes) > 1000) {
                $this->addFlash('danger', 'Bitte einen gültigen Rückgabezustand angeben (maximal 1000 Zeichen Bemerkung).');
                return $this->redirectToRoute('equipment_manager_loans', [], Response::HTTP_SEE_OTHER);
            }
        }
        try {
            $service->change($bookingId, $lineId, $operation, $condition, $notes ?: null);
            $this->addFlash('success', $operation === 'issue' ? 'Gegenstand ausgegeben.' : 'Rücknahme gespeichert.');
        } catch (\DomainException $e) {
            $this->addFlash('danger', $e->getMessage());
        }
        return $this->redirectToRoute('equipment_manager_loans', ['checkout' => $bookingId], Response::HTTP_SEE_OTHER);
    }
}
