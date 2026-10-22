<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\MemberTankCheckBooking;
use App\Entity\MemberTankCheckBookingItem;
use App\Entity\TankCheck;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_SUPER_ADMIN')]
final class TuvMemberOrderExportController extends AbstractController
{
    #[Route('/admin/tuv/check/{id}/member-orders.csv', name: 'admin_tuv_member_orders_csv', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function export(int $id, Request $request, EntityManagerInterface $em): Response
    {
        $check = $em->find(TankCheck::class, $id);
        if (!$check) {
            throw $this->createNotFoundException('TÜV-Termin nicht gefunden.');
        }

        // Default export uses references only. Names require an explicit opt-in.
        $includeNames = $request->query->get('names') === '1';
        $bookings = $em->getRepository(MemberTankCheckBooking::class)->findBy(['tankCheck' => $check], ['id' => 'ASC']);
        $out = fopen('php://temp', 'w+');
        if ($out === false) {
            throw new \RuntimeException('CSV konnte nicht erzeugt werden.');
        }
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Auftragsreferenz', 'Mitgliedsnummer', 'Mitgliedsname', 'Flaschen-ID', 'Flaschenbezeichnung', 'Seriennummer', 'Pruefleistung', 'Menge', 'Brutto EUR', 'Status'], ';');
        foreach ($bookings as $booking) {
            $member = $booking->getMember();
            $tank = $booking->getMemberTank();
            if (!$member || !$tank) {
                continue; // Club-owned cylinders are not individual member orders.
            }
            $items = $em->getRepository(MemberTankCheckBookingItem::class)->findBy(['booking' => $booking], ['id' => 'ASC']);
            foreach ($items as $item) {
                $amount = number_format((float) $item->getGrossSnapshot() * $item->getQuantity(), 2, ',', '');
                $cells = [
                    'TUV-'.$id.'-'.$booking->getId(),
                    $member->getMemberNumber() ?? '',
                    $includeNames ? $member->getFullName() : '',
                    (string) $tank->getId(),
                    $tank->getLabel(),
                    $tank->getSerialNumber(),
                    $item->getTitleSnapshot(),
                    (string) $item->getQuantity(),
                    $amount,
                    $booking->getStatus(),
                ];
                // Prevent spreadsheet formula injection in user-controlled text fields.
                $cells = array_map(static function (string $v): string {
                    return preg_match('/^[\s]*[=+\-@]/u', $v) ? "'".$v : $v;
                }, $cells);
                fputcsv($out, $cells, ';');
            }
        }
        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);
        return new Response($csv === false ? '' : $csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="tuv-member-orders-'.$id.'.csv"',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
