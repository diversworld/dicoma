<?php
declare(strict_types=1);
namespace App\Controller\Admin;

use App\Entity\MemberTankCheckBookingItem;
use App\Entity\TankCheck;
use App\Entity\TuvGroupBooking;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_SUPER_ADMIN')]
final class TuvSupplierReconciliationController extends AbstractController
{
    /** @return array<int,array<string,mixed>> */
    private function lines(int $id, EntityManagerInterface $em): array
    {
        $check = $em->getRepository(TankCheck::class)->find($id);
        if (!$check) { throw $this->createNotFoundException('TÜV-Termin nicht gefunden.'); }
        $links = $em->getRepository(TuvGroupBooking::class)->findAll();
        $rows = [];
        foreach ($links as $link) {
            $group = $link->getGroup();
            if ($group->getTankCheck()->getId() !== $id || !$group->getAgencyConfirmedAt()) { continue; }
            $booking = $link->getBooking();
            if ($booking->getTankCheck()?->getId() !== $id || !$booking->getMemberTank() || !$booking->getMember()) { continue; }
            $items = $em->getRepository(MemberTankCheckBookingItem::class)->findBy(['booking' => $booking]);
            $cents = 0;
            $labels = [];
            foreach ($items as $item) {
                $cents += (int) round(((float) $item->getGrossSnapshot()) * 100) * $item->getQuantity();
                $labels[] = $item->getTitleSnapshot().' ('.$item->getQuantity().'x)';
            }
            $rows[] = [
                'reference' => 'TUV-'.$id.'-'.$booking->getId(),
                'member' => $booking->getMember()->getMemberNumber() ?: 'ID-'.$booking->getMember()->getId(),
                'tank' => $booking->getMemberTank()->getSerialNumber(),
                'services' => implode(' / ', $labels),
                'expected' => $cents,
                'complete' => $items !== [],
                'group' => $group->getId(),
                'confirmed' => $group->getAgencyConfirmedAt()->format('Y-m-d H:i:s'),
            ];
        }
        usort($rows, static fn (array $a, array $b): int => strcmp($a['reference'], $b['reference']));
        return $rows;
    }

    #[Route('/admin/tuv/check/{id}/supplier-reconciliation.csv', name: 'admin_tuv_supplier_reconciliation_csv', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function csv(int $id, EntityManagerInterface $em): Response
    {
        $rows = $this->lines($id, $em);
        $response = new StreamedResponse(static function () use ($rows): void {
            $out = fopen('php://output', 'wb');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Auftragsreferenz', 'Mitgliedsnummer', 'Flaschen-Seriennummer', 'Leistungen', 'Erwartet EUR', 'Tatsächlich EUR', 'Differenz EUR', 'Gruppen-ID', 'Bestätigung'], ';');
            foreach ($rows as $row) {
                $safe = static fn (string $v): string => preg_match('/^[=+@\-\t\r]/u', $v) ? "'".$v : $v;
                fputcsv($out, [
                    $safe($row['reference']), $safe($row['member']), $safe($row['tank']), $safe($row['services']),
                    number_format($row['expected'] / 100, 2, ',', ''), '', '', $row['group'], $row['confirmed'],
                ], ';');
            }
            fclose($out);
        });
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="tuv-abgleich-'.$id.'.csv"');
        $response->headers->set('Cache-Control', 'private, no-store');
        return $response;
    }

    #[Route('/admin/tuv/check/{id}/supplier-reconciliation', name: 'admin_tuv_supplier_reconciliation', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    public function index(int $id, Request $request, EntityManagerInterface $em): Response
    {
        $rows = $this->lines($id, $em);
        $actual = [];
        $errors = [];
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('tuv_supplier_reconciliation_'.$id, (string) $request->request->get('_token', ''))) {
                throw $this->createAccessDeniedException('Ungültiger CSRF-Token.');
            }
            $input = $request->request->all('actual');
            $known = array_column($rows, 'reference');
            foreach ($input as $reference => $amount) {
                if (!is_string($reference) || !in_array($reference, $known, true) || !is_string($amount) || trim($amount) === '') { continue; }
                $normalized = str_replace(',', '.', trim($amount));
                if (!preg_match('/^\d{1,8}(?:\.\d{1,2})?$/', $normalized)) {
                    $errors[] = 'Ungültiger Betrag bei '.$reference;
                    continue;
                }
                $actual[$reference] = (int) round(((float) $normalized) * 100);
            }
        }
        return $this->render('admin/tuv_supplier_reconciliation.html.twig', [
            'checkId' => $id, 'rows' => $rows, 'actual' => $actual, 'errors' => $errors,
        ]);
    }
}
