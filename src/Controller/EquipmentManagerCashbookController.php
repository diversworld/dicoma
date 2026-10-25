<?php
declare(strict_types=1);
namespace App\Controller;
use App\Entity\Club;
use App\Service\EquipmentCashGuard;
use App\Entity\EquipmentBooking;
use App\Entity\EquipmentCashPayout;
use App\Entity\EquipmentRentalReceipt;
use App\Entity\EquipmentFillCharge;
use App\Entity\MemberTankCheckBooking;
use App\Entity\TuvPaymentReceipt;
use App\Entity\User;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
#[IsGranted('ROLE_EQUIPMENT_MANAGER')]
final class EquipmentManagerCashbookController extends AbstractController
{
    private function clubId(): int
    {
        $u=$this->getUser(); $id=$u instanceof User ? $u->getMember()?->getClub()?->getId() : null;
        if (!$id) { throw $this->createAccessDeniedException('Kein Verein zugeordnet.'); }
        return $id;
    }
    #[Route('/equipment-manager/cashbook', name: 'equipment_manager_cashbook', methods: ['GET'])]
    public function index(EntityManagerInterface $em): Response
    {
        $clubId=$this->clubId(); $rows=[]; $totals=['tuv'=>0,'rental'=>0,'fill'=>0,'cash'=>0,'bank'=>0];
        $tuv=$em->getRepository(TuvPaymentReceipt::class)->createQueryBuilder('r')
            ->join('r.booking','b')->join('b.member','m')->join('m.club','c')
            ->where('c.id = :club')->setParameter('club',$clubId)->orderBy('r.collectedAt','DESC')->setMaxResults(500)->getQuery()->getResult();
        $rentals=$em->getRepository(EquipmentRentalReceipt::class)->createQueryBuilder('r')
            ->join('r.booking','b')->join('b.member','m')->join('m.club','c')
            ->where('c.id = :club')->setParameter('club',$clubId)->orderBy('r.collectedAt','DESC')->setMaxResults(500)->getQuery()->getResult();
        foreach ([['tuv',$tuv],['rental',$rentals]] as [$type,$receipts]) {
            foreach ($receipts as $r) {
                $rows[]=['type'=>$type,'receipt'=>$r]; $totals[$type]+=$r->getAmountCents();
                $totals[$r->getMethod()]+=$r->getAmountCents();
            }
        }
        $fills=$em->getRepository(EquipmentFillCharge::class)->createQueryBuilder('r')
            ->where('IDENTITY(r.club) = :club AND r.collectedAt IS NOT NULL')->setParameter('club',$clubId)
            ->orderBy('r.collectedAt','DESC')->setMaxResults(500)->getQuery()->getResult();
        foreach ($fills as $receipt) {
            $rows[]=['type'=>'fill','receipt'=>$receipt];
            $totals['fill']+=$receipt->getAmountCents();
            $totals[$receipt->getMethod()]+=$receipt->getAmountCents();
        }
        // Auch echte Barauszahlungen gehören chronologisch ins Hauptjournal.
        $payouts=$em->getRepository(EquipmentCashPayout::class)->createQueryBuilder('p')
            ->where('IDENTITY(p.club) = :club')->setParameter('club',$clubId)
            ->orderBy('p.paidAt','DESC')->addOrderBy('p.id','DESC')
            ->setMaxResults(500)->getQuery()->getResult();
        foreach ($payouts as $payout) {
            $rows[]=['type'=>'payout','payout'=>$payout];
        }
        // Bar-Korrekturen sind eigenständige Kassenbewegungen; Originalbelege bleiben sichtbar.
        $corrections=$em->getConnection()->fetchAllAssociative(
            'SELECT id, source_type, source_id, direction, amount_cents, reason, created_by, created_at FROM equipment_cash_correction WHERE club_id = :club ORDER BY created_at DESC, id DESC LIMIT 500',
            ['club'=>$clubId]
        );
        foreach ($corrections as $correction) {
            $rows[]=['type'=>'correction','correction'=>$correction];
        }
        usort($rows,static function(array $a,array $b):int {
            $dateA=match ($a['type']) {
                'correction' => $a['correction']['created_at'],
                'payout' => $a['payout']->getPaidAt()->format('Y-m-d H:i:s'),
                default => $a['receipt']->getCollectedAt()->format('Y-m-d H:i:s'),
            };
            $dateB=match ($b['type']) {
                'correction' => $b['correction']['created_at'],
                'payout' => $b['payout']->getPaidAt()->format('Y-m-d H:i:s'),
                default => $b['receipt']->getCollectedAt()->format('Y-m-d H:i:s'),
            };
            return strcmp($dateB,$dateA);
        });
        $openTuv=$em->getRepository(MemberTankCheckBooking::class)->createQueryBuilder('b')
            ->join('b.member','m')->join('m.club','c')->leftJoin(TuvPaymentReceipt::class,'r','WITH','r.booking = b')
            ->where('c.id = :club AND r.id IS NULL')->setParameter('club',$clubId)
            ->orderBy('b.id','DESC')->setMaxResults(100)->getQuery()->getResult();
        return $this->render('equipment_manager/cashbook.html.twig',['rows'=>$rows,'totals'=>$totals,'openTuv'=>$openTuv]);
    }
    #[Route('/equipment-manager/loans/{id}/collect-fee', name: 'equipment_manager_collect_rental_fee', requirements: ['id'=>'\\d+'], methods: ['POST'])]
    // DICOMA_CHECKOUT_RETURN_V1
    public function collectRental(int $id, Request $request, EntityManagerInterface $em, EquipmentCashGuard $guard): Response
    {
        $clubId=$this->clubId(); $booking=$em->getRepository(EquipmentBooking::class)->find($id);
        if (!$booking || $booking->getMember()?->getClub()?->getId() !== $clubId) { throw $this->createNotFoundException(); }
        if (!$this->isCsrfTokenValid('rental_collect_'.$id,(string)$request->request->get('_token',''))) { throw $this->createAccessDeniedException('Ungueltiger CSRF-Token.'); }
        if (!in_array($booking->getStatus(),['partially_issued','issued','partially_returned','returned'],true)) {
            $this->addFlash('danger','Leihgebuehr erst nach tatsaechlicher Ausgabe erfassen.');
            return $this->redirectToRoute('equipment_manager_loans', ['checkout' => $id], Response::HTTP_SEE_OTHER);
        }
        if ($em->getRepository(EquipmentRentalReceipt::class)->findOneBy(['booking'=>$booking])) {
            $this->addFlash('danger','Diese Buchung wurde bereits kassiert.'); return $this->redirectToRoute('equipment_manager_loans', ['checkout' => $id], Response::HTTP_SEE_OTHER);
        }
        $raw=str_replace(',','.',trim((string)$request->request->get('amount','')));
        if (!preg_match('/^\\d{1,7}(?:\\.\\d{1,2})?$/D',$raw)) { $this->addFlash('danger','Ungueltiger Betrag.'); return $this->redirectToRoute('equipment_manager_loans', ['checkout' => $id], Response::HTTP_SEE_OTHER); }
        [$euros,$cents]=array_pad(explode('.',$raw,2),2,''); $amount=(int)$euros*100+(int)str_pad($cents,2,'0');
        $method=(string)$request->request->get('method','');
        if ($amount<=0 || !in_array($method,['cash','bank'],true)) { $this->addFlash('danger','Betrag oder Zahlungsart ungueltig.'); return $this->redirectToRoute('equipment_manager_loans', ['checkout' => $id], Response::HTTP_SEE_OTHER); }
        $actor=$this->getUser()?->getUserIdentifier() ?? 'unbekannt';
        $db=$em->getConnection(); $db->beginTransaction();
        try {
            $guard->lockAndAssertOpen($em->getReference(Club::class,$clubId));
            $em->refresh($booking, \Doctrine\DBAL\LockMode::PESSIMISTIC_WRITE);
            if ($booking->getMember()?->getClub()?->getId() !== $clubId || !in_array($booking->getStatus(), ['partially_issued','issued','partially_returned','returned'], true)) {
                throw new \DomainException('Buchung ist nicht zur Zahlungsverbuchung freigegeben.');
            }
            if ($booking->getAgreedRentalFeeCents() === null || $amount !== $booking->getAgreedRentalFeeCents()) {
                throw new \DomainException('Betrag muss der festgeschriebenen Leihgebühr entsprechen; fehlenden Altpreis zuerst bestätigen.');
            }
            if ($em->getRepository(EquipmentRentalReceipt::class)->findOneBy(['booking'=>$booking])) { throw new \DomainException('Zahlung bereits erfasst.'); }
            $em->persist(new EquipmentRentalReceipt($booking,$amount,$method,$actor,(string)$request->request->get('note','')));
            $em->flush(); $db->commit();
            $this->addFlash('success','Leihgebuehr kassiert und im Kassenbuch erfasst.');
        } catch (\DomainException|UniqueConstraintViolationException $e) {
            if ($db->isTransactionActive()) {$db->rollBack();}
            $this->addFlash('danger',$e instanceof \DomainException ? $e->getMessage() : 'Zahlung bereits erfasst.');
        } catch (\Throwable $e) {if ($db->isTransactionActive()) {$db->rollBack();} throw $e;}
        return $this->redirectToRoute('equipment_manager_loans', ['checkout' => $id], Response::HTTP_SEE_OTHER);
    }
    #[Route('/equipment-manager/tuv/booking/{id}/collect-payment', name: 'equipment_manager_tuv_collect_payment', requirements: ['id'=>'\\d+'], methods: ['POST'])]
    public function collectTuv(int $id, Request $request, EntityManagerInterface $em, EquipmentCashGuard $guard): Response
    {
        $clubId=$this->clubId(); $booking=$em->getRepository(MemberTankCheckBooking::class)->find($id);
        if (!$booking || !$booking->getMemberTank() || $booking->getMember()?->getClub()?->getId() !== $clubId) { throw $this->createNotFoundException(); }
        if (!$this->isCsrfTokenValid('tuv_collect_'.$id,(string)$request->request->get('_token',''))) { throw $this->createAccessDeniedException(); }
        if ($em->getRepository(TuvPaymentReceipt::class)->findOneBy(['booking'=>$booking])) { $this->addFlash('danger','Zahlung bereits erfasst.'); return $this->redirectToRoute('equipment_manager_cashbook'); }
        $raw=str_replace(',','.',trim((string)$request->request->get('amount','')));
        if (!preg_match('/^\\d{1,7}(?:\\.\\d{1,2})?$/D',$raw)) { $this->addFlash('danger','Ungueltiger Betrag.'); return $this->redirectToRoute('equipment_manager_cashbook'); }
        [$euros,$cents]=array_pad(explode('.',$raw,2),2,''); $amount=(int)$euros*100+(int)str_pad($cents,2,'0');
        $method=(string)$request->request->get('method','');
        if ($amount<=0 || !in_array($method,['cash','bank'],true)) { $this->addFlash('danger','Betrag oder Zahlungsart ungueltig.'); return $this->redirectToRoute('equipment_manager_cashbook'); }
        $db=$em->getConnection(); $db->beginTransaction();
        try {
            $guard->lockAndAssertOpen($em->getReference(Club::class,$clubId));
            if ($em->getRepository(TuvPaymentReceipt::class)->findOneBy(['booking'=>$booking])) { throw new \DomainException('Zahlung bereits erfasst.'); }
            $em->persist(new TuvPaymentReceipt($booking,$amount,$method,$this->getUser()?->getUserIdentifier()??'unbekannt',(string)$request->request->get('note','')));
            $em->flush(); $db->commit();
            $this->addFlash('success','TUEV-Zahlung im Kassenbuch erfasst.');
        } catch (\DomainException|UniqueConstraintViolationException $e) {
            if ($db->isTransactionActive()) {$db->rollBack();}
            $this->addFlash('danger',$e instanceof \DomainException ? $e->getMessage() : 'Zahlung bereits erfasst.');
        } catch (\Throwable $e) {if ($db->isTransactionActive()) {$db->rollBack();} throw $e;}
        return $this->redirectToRoute('equipment_manager_cashbook');
    }
}
