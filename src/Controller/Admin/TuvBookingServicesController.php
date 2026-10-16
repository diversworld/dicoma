<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\MemberTankCheckBooking;
use App\Entity\MemberTankCheckBookingItem;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class TuvBookingServicesController extends AbstractController
{
    #[Route('/admin/tuv/booking/{id}/services', name: 'admin_tuv_booking_services', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function show(MemberTankCheckBooking $booking, EntityManagerInterface $em): Response
    {
        $items = $em->getRepository(MemberTankCheckBookingItem::class)
            ->findBy(['booking' => $booking], ['id' => 'ASC']);

        $rows = [];
        $netCents = 0;
        $grossCents = 0;
        foreach ($items as $item) {
            $quantity = $item->getQuantity();
            $net = $this->toCents($item->getNetSnapshot());
            $gross = $this->toCents($item->getGrossSnapshot());
            $rows[] = [
                'title' => $item->getTitleSnapshot(),
                'quantity' => $quantity,
                'net' => $net,
                'gross' => $gross,
                'netTotal' => $net * $quantity,
                'grossTotal' => $gross * $quantity,
            ];
            $netCents += $net * $quantity;
            $grossCents += $gross * $quantity;
        }

        return $this->render('admin/tuv/booking_services.html.twig', [
            'booking' => $booking,
            'rows' => $rows,
            'netCents' => $netCents,
            'grossCents' => $grossCents,
        ]);
    }

    private function toCents(string $decimal): int
    {
        if (!preg_match('/^-?\\d+\\.\\d{2}$/D', $decimal)) {
            throw new \UnexpectedValueException('Ungültiger gespeicherter Preis.');
        }
        $negative = str_starts_with($decimal, '-');
        [$whole, $fraction] = explode('.', ltrim($decimal, '-'), 2);
        $cents = ((int) $whole * 100) + (int) $fraction;
        return $negative ? -$cents : $cents;
    }
}
