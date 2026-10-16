<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\MemberTankCheckBooking;
use App\Entity\MemberTankCheckBookingItem;
use App\Entity\TankCheck;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class TuvBookingOverviewController extends AbstractController
{
    private const NEXT_STATUSES = [
        'requested' => ['received'],
        'received' => ['sent'],
        'sent' => ['returned'],
        'returned' => ['completed'],
    ];

    #[Route('/admin/tuv/bookings/{id}/status', name: 'admin_tuv_booking_status', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function changeStatus(int $id, Request $request, EntityManagerInterface $em): Response
    {
        $booking = $em->getRepository(MemberTankCheckBooking::class)->find($id);
        if ($booking === null) {
            throw $this->createNotFoundException('TÜV-Anmeldung nicht gefunden.');
        }
        if (!$this->isCsrfTokenValid('admin_tuv_booking_status_'.$id, (string) $request->request->get('_token', ''))) {
            throw $this->createAccessDeniedException('Ungültiger CSRF-Token.');
        }
        $target = $request->request->get('status', '');
        if (!is_string($target) || !in_array($target, self::NEXT_STATUSES[$booking->getStatus()] ?? [], true)) {
            $this->addFlash('danger', 'Dieser Statuswechsel ist nicht erlaubt.');
        } else {
            $booking->setStatus($target);
            $em->flush();
            $this->addFlash('success', 'Bearbeitungsstatus aktualisiert.');
        }
        return $this->redirectToRoute('admin_tuv_booking_overview', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/admin/tuv/bookings', name: 'admin_tuv_booking_overview', methods: ['GET'])]
    public function index(EntityManagerInterface $em): Response
    {
        $checks = $em->getRepository(TankCheck::class)->findBy([], ['checkDate' => 'DESC', 'id' => 'DESC']);
        $bookings = $em->getRepository(MemberTankCheckBooking::class)->findBy([], ['createdAt' => 'DESC', 'id' => 'DESC']);
        $items = $em->getRepository(MemberTankCheckBookingItem::class)->findBy([], ['id' => 'ASC']);
        $byBooking = [];
        foreach ($items as $item) {
            $id = $item->getBooking()?->getId();
            if ($id === null) { continue; }
            $byBooking[$id][] = $item;
        }
        $grouped = [];
        foreach ($bookings as $booking) {
            $checkId = $booking->getTankCheck()?->getId();
            if ($checkId === null) { continue; }
            $net = 0;
            $gross = 0;
            foreach ($byBooking[$booking->getId()] ?? [] as $item) {
                $net += (int) round((float) $item->getNetSnapshot() * 100) * $item->getQuantity();
                $gross += (int) round((float) $item->getGrossSnapshot() * 100) * $item->getQuantity();
            }
            $grouped[$checkId][] = [
                'booking' => $booking,
                'items' => $byBooking[$booking->getId()] ?? [],
                'net' => $net / 100,
                'gross' => $gross / 100,
            ];
        }
        return $this->render('admin/tuv/bookings.html.twig', [
            'checks' => $checks,
            'grouped' => $grouped,
        ]);
    }
}
