<?php
declare(strict_types=1);
namespace App\Controller;

use App\Entity\EquipmentRentalReceipt;
use App\Entity\EquipmentFillCharge;
use App\Entity\EquipmentCashPayout;
use App\Entity\TuvPaymentReceipt;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_EQUIPMENT_MANAGER')]
final class EquipmentManagerDailyCashReportController extends AbstractController
{
    #[Route('/equipment-manager/cashbook/day', name: 'equipment_manager_cashbook_day', methods: ['GET'])]
    public function day(Request $request, EntityManagerInterface $em): Response
    {
        [$date, $rows, $summary] = $this->data($request, $em);
        return $this->render('equipment_manager/cashbook_day.html.twig', compact('date', 'rows', 'summary'));
    }

    #[Route('/equipment-manager/cashbook/day.csv', name: 'equipment_manager_cashbook_day_csv', methods: ['GET'])]
    public function csv(Request $request, EntityManagerInterface $em): Response
    {
        [$date, $rows, $summary] = $this->data($request, $em);
        $response = new StreamedResponse(static function () use ($rows, $summary, $date): void {
            $out = fopen('php://output', 'wb');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Datum', $date], ';', escape: '\\');
            fputcsv($out, ['Zeit', 'Art', 'Buchung', 'Zahlungsart', 'Betrag EUR', 'Kassiert von', 'Beleg-ID'], ';', escape: '\\');
            foreach ($rows as $row) {
                $r = $row['receipt'];
                if ($row['type']==='correction') {fputcsv($out,[(new \DateTimeImmutable($r['created_at']))->format('d.m.Y H:i:s'),'Korrektur: '.$r['source_type'].' #'.$r['source_id'],$r['reason'],'Bar',number_format(($r['direction']==='in'?1:-1)*(int)$r['amount_cents']/100,2,',',''),$r['created_by'],'correction-'.$r['id']],';', escape: '\\');continue;}
                fputcsv($out, [($row['type'] === 'payout' ? $r->getPaidAt() : $r->getCollectedAt())->format('d.m.Y H:i:s'), $row['type'] === 'payout' ? 'Barauszahlung: '.$r->getCategory().' / '.$r->getRecipient() : ($row['type'] === 'fill' ? 'Füllgebühr' : ($row['type'] === 'tuv' ? 'TÜV' : 'Leihgebühr')), $row['type'] === 'payout' ? $r->getPurpose() : ($row['type'] === 'fill' ? $r->getLabel() : $r->getBooking()->getId()), $row['type'] === 'payout' ? 'Bar ausgezahlt' : ($r->getMethod() === 'cash' ? 'Bar' : 'Überweisung'), number_format(($row['type'] === 'payout' ? -1 : 1)*$r->getAmountCents()/100, 2, ',', ''), $row['type'] === 'payout' ? $r->getPaidBy() : $r->getCollectedBy(), $row['type'].'-'.$r->getId()], ';', escape: '\\');
            }
            fputcsv($out, ['Bar-Einnahmen EUR', number_format(($summary['cash']+$summary['correction_in'])/100, 2, ',', '')], ';', escape: '\\');
            fputcsv($out, ['Bar-Auszahlungen EUR', number_format(($summary['payout']+$summary['correction_out'])/100, 2, ',', '')], ';', escape: '\\');
            fputcsv($out, ['Bar-Korrekturen Eingang EUR', number_format($summary['correction_in']/100, 2, ',', '')], ';', escape: '\\');
            fputcsv($out, ['Bar-Korrekturen Ausgang EUR', number_format($summary['correction_out']/100, 2, ',', '')], ';', escape: '\\');
            fputcsv($out, ['Netto-Barbewegung EUR', number_format(($summary['cash']+$summary['correction_in']-$summary['payout']-$summary['correction_out'])/100, 2, ',', '')], ';', escape: '\\');
            fputcsv($out, ['Überweisung EUR', number_format($summary['bank']/100, 2, ',', '')], ';', escape: '\\');
            fputcsv($out, ['Gesamt EUR', number_format(($summary['cash']+$summary['bank'])/100, 2, ',', '')], ';', escape: '\\');
            fclose($out);
        });
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="geraetewart-kasse-'.$date.'.csv"');
        $response->headers->set('Cache-Control', 'private, no-store');
        return $response;
    }

    private function data(Request $request, EntityManagerInterface $em): array
    {
        $user = $this->getUser();
        $clubId = $user instanceof User ? $user->getMember()?->getClub()?->getId() : null;
        if (!$clubId) { throw $this->createAccessDeniedException('Kein Verein zugeordnet.'); }
        $date = (string) $request->query->get('date', date('Y-m-d'));
        $start = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (!$start || $start->format('Y-m-d') !== $date) { throw $this->createNotFoundException('Ungültiges Datum.'); }
        $end = $start->modify('+1 day');
        $rows = []; $summary = ['cash'=>0, 'bank'=>0, 'tuv'=>0, 'rental'=>0, 'fill'=>0, 'payout'=>0,'correction_in'=>0,'correction_out'=>0];
        foreach ([['tuv', TuvPaymentReceipt::class], ['rental', EquipmentRentalReceipt::class]] as [$type, $entity]) {
            $receipts = $em->getRepository($entity)->createQueryBuilder('r')
                ->join('r.booking', 'b')->join('b.member', 'm')->join('m.club', 'c')
                ->where('c.id = :club')->andWhere('r.collectedAt >= :start')->andWhere('r.collectedAt < :end')
                ->setParameter('club', $clubId)->setParameter('start', $start)->setParameter('end', $end)
                ->orderBy('r.collectedAt', 'ASC')->addOrderBy('r.id', 'ASC')->getQuery()->getResult();
            foreach ($receipts as $receipt) {
                $rows[] = ['type'=>$type, 'receipt'=>$receipt];
                $summary[$type] += $receipt->getAmountCents();
                $summary[$receipt->getMethod()] += $receipt->getAmountCents();
            }
        }
        $fills=$em->getRepository(EquipmentFillCharge::class)->createQueryBuilder('r')
            ->where('IDENTITY(r.club) = :club AND r.collectedAt >= :start AND r.collectedAt < :end')
            ->setParameter('club',$clubId)->setParameter('start',$start)->setParameter('end',$end)
            ->getQuery()->getResult();
        foreach ($fills as $receipt) {
            $rows[]=['type'=>'fill','receipt'=>$receipt];
            $summary['fill']+=$receipt->getAmountCents();
            $summary[$receipt->getMethod()]+=$receipt->getAmountCents();
        }
        $payouts = $em->getRepository(EquipmentCashPayout::class)->createQueryBuilder('p')
            ->where('p.club = :club')->andWhere('p.paidAt >= :start')->andWhere('p.paidAt < :end')
            ->setParameter('club', $clubId)->setParameter('start', $start)->setParameter('end', $end)->getQuery()->getResult();
        foreach ($payouts as $payout) { $rows[] = ['type'=>'payout', 'receipt'=>$payout]; $summary['payout'] += $payout->getAmountCents(); }
        $corrections=$em->getConnection()->fetchAllAssociative('SELECT * FROM equipment_cash_correction WHERE club_id=:club AND created_at>=:start AND created_at<:end',['club'=>$clubId,'start'=>$start->format('Y-m-d H:i:s'),'end'=>$end->format('Y-m-d H:i:s')]);
        foreach ($corrections as $r) {$rows[]=['type'=>'correction','receipt'=>$r];$summary[$r['direction']==='in'?'correction_in':'correction_out']+=(int)$r['amount_cents'];}
        usort($rows, static fn(array $a, array $b): int => (($a['type']==='correction' ? new \DateTimeImmutable($a['receipt']['created_at']) : ($a['type'] === 'payout' ? $a['receipt']->getPaidAt() : $a['receipt']->getCollectedAt())) <=> ($b['type']==='correction' ? new \DateTimeImmutable($b['receipt']['created_at']) : ($b['type'] === 'payout' ? $b['receipt']->getPaidAt() : $b['receipt']->getCollectedAt()))) ?: strcmp($a['type'], $b['type']));
        return [$date, $rows, $summary];
    }
}
