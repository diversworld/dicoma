<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\MemberTankCheckBooking;
use App\Entity\MemberTankCheckBookingItem;
use App\Entity\User;
use App\Entity\TuvDocument;
use App\Entity\TuvInvoice;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_MEMBER')]
final class MemberTankCheckBookingDetailController extends AbstractController
{
    #[Route('/my/equipment/bookings/{id}', name: 'member_tank_check_booking_detail', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User || $user->getMember() === null) {
            throw $this->createAccessDeniedException('Kein Mitgliedsprofil.');
        }

        // Scope the query to the logged-in member to prevent viewing other members' bookings.
        $booking = $em->getRepository(MemberTankCheckBooking::class)->findOneBy([
            'id' => $id,
            'member' => $user->getMember(),
        ]);
        if ($booking === null) {
            throw $this->createNotFoundException('Anmeldung nicht gefunden.');
        }

        $items = $em->getRepository(MemberTankCheckBookingItem::class)->findBy(
            ['booking' => $booking], ['id' => 'ASC']
        );
        $netCents = 0;
        $grossCents = 0;
        foreach ($items as $item) {
            $qty = $item->getQuantity();
            $netCents += (int) round((float) $item->getNetSnapshot() * 100) * $qty;
            $grossCents += (int) round((float) $item->getGrossSnapshot() * 100) * $qty;
        }

        return $this->render('member_equipment/booking_detail.html.twig', [
            'booking' => $booking,
            'items' => $items,
            'netTotal' => $netCents / 100,
            'grossTotal' => $grossCents / 100,
            'documents' => $em->getRepository(TuvDocument::class)->findBy(['booking' => $booking], ['id' => 'DESC']),
            'invoice' => $em->getRepository(TuvInvoice::class)->findOneBy(['booking' => $booking]),
        ]);
    }
}
