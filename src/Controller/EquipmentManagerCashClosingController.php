<?php
declare(strict_types=1);
namespace App\Controller;

use App\Entity\Club;
use App\Entity\EquipmentCashClosing;
use App\Entity\User;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_EQUIPMENT_MANAGER')]
final class EquipmentManagerCashClosingController extends AbstractController
{
    private function club(EntityManagerInterface $em): Club
    {
        $user=$this->getUser();
        $id=$user instanceof User ? $user->getMember()?->getClub()?->getId() : null;
        if (!$id || !$club=$em->find(Club::class,$id)) {throw $this->createAccessDeniedException('Kein Verein zugeordnet.');}
        return $club;
    }
    private function cents(string $value): ?int
    {
        $value=str_replace(',', '.', trim($value));
        if (!preg_match('/^\d{1,7}(?:\.\d{1,2})?$/D',$value)) {return null;}
        [$euros,$fraction]=array_pad(explode('.',$value,2),2,'');
        return (int)$euros*100+(int)str_pad($fraction,2,'0');
    }
    private function movements(EntityManagerInterface $em, int $clubId, string $date): array
    {
        $db=$em->getConnection();
        $start=$date.' 00:00:00'; $end=(new \DateTimeImmutable($date))->modify('+1 day')->format('Y-m-d').' 00:00:00';
        $items=[]; $cashIn=0; $cashOut=0;
        foreach (['tuv_payment_receipt'=>'tuv','equipment_rental_receipt'=>'rental'] as $table=>$kind) {
            $booking=$kind==='tuv'?'member_tank_check_booking':'equipment_booking';
            $rows=$db->fetchAllAssociative("SELECT r.id,r.amount_cents,r.collected_at FROM $table r INNER JOIN $booking b ON b.id=r.booking_id INNER JOIN member m ON m.id=b.member_id WHERE m.club_id=:club AND r.method='cash' AND r.collected_at>=:start AND r.collected_at<:end", ['club'=>$clubId,'start'=>$start,'end'=>$end]);
            foreach ($rows as $r) {$cashIn+=(int)$r['amount_cents'];$items[]=$kind.':'.$r['id'].':'.$r['amount_cents'].':'.$r['collected_at'];}
        }
        $rows=$db->fetchAllAssociative("SELECT id,amount_cents,collected_at FROM equipment_fill_charge WHERE club_id=:club AND method='cash' AND collected_at>=:start AND collected_at<:end", ['club'=>$clubId,'start'=>$start,'end'=>$end]);
        foreach ($rows as $r) {$cashIn+=(int)$r['amount_cents'];$items[]='fill:'.$r['id'].':'.$r['amount_cents'].':'.$r['collected_at'];}
        $rows=$db->fetchAllAssociative('SELECT id,amount_cents,paid_at FROM equipment_cash_payout WHERE club_id=:club AND paid_at>=:start AND paid_at<:end', ['club'=>$clubId,'start'=>$start,'end'=>$end]);
        foreach ($rows as $r) {$cashOut+=(int)$r['amount_cents'];$items[]='payout:'.$r['id'].':'.$r['amount_cents'].':'.$r['paid_at'];}
        $corrections=$db->fetchAllAssociative('SELECT id,amount_cents,direction,created_at FROM equipment_cash_correction WHERE club_id=:club AND created_at>=:start AND created_at<:end', ['club'=>$clubId,'start'=>$start,'end'=>$end]);
        foreach ($corrections as $r) {if ($r['direction']==='in') {$cashIn+=(int)$r['amount_cents'];} else {$cashOut+=(int)$r['amount_cents'];}$items[]='correction:'.$r['id'].':'.$r['direction'].':'.$r['amount_cents'].':'.$r['created_at'];}
        sort($items,SORT_STRING);
        return ['in'=>$cashIn,'out'=>$cashOut,'hash'=>hash('sha256',implode("\n",$items)),'count'=>count($items)];
    }
    #[Route('/equipment-manager/cashbook/closing', name:'equipment_manager_cash_closing', methods:['GET','POST'])]
    public function closing(Request $request, EntityManagerInterface $em): Response
    {
        $club=$this->club($em); $clubId=(int)$club->getId();
        $date=(string)($request->isMethod('POST')?$request->request->get('date',''):$request->query->get('date',date('Y-m-d')));
        $day=\DateTimeImmutable::createFromFormat('!Y-m-d',$date);
        if (!$day || $day->format('Y-m-d')!==$date || $date>date('Y-m-d')) {throw $this->createNotFoundException('Ungültiges Abschlussdatum.');}
        $repo=$em->getRepository(EquipmentCashClosing::class);
        $findPrevious=fn()=> $repo->createQueryBuilder('c')->where('c.club = :club')->andWhere('c.closingDate < :date')->setParameter('club',$club)->setParameter('date',$day)->orderBy('c.closingDate','DESC')->setMaxResults(1)->getQuery()->getOneOrNullResult();
        $findLater=fn()=> $repo->createQueryBuilder('c')->where('c.club = :club')->andWhere('c.closingDate > :date')->setParameter('club',$club)->setParameter('date',$day)->setMaxResults(1)->getQuery()->getOneOrNullResult();
        $existing=$repo->findOneBy(['club'=>$club,'closingDate'=>$day]);
        $previous=$findPrevious(); $later=$findLater();
        $movements=$this->movements($em,$clubId,$date);
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('cash_close_'.$clubId.'_'.$date,(string)$request->request->get('_token',''))) {throw $this->createAccessDeniedException('Ungültiger CSRF-Token.');}
            $counted=$this->cents((string)$request->request->get('counted',''));
            $opening=$previous ? $previous->getCountedCents() : $this->cents((string)$request->request->get('opening',''));
            $note=trim((string)$request->request->get('note','')); $handover=trim((string)$request->request->get('handover',''));
            if ($existing || $later || $counted===null || $opening===null || mb_strlen($note)>500 || mb_strlen($handover)>180) {
                $this->addFlash('danger','Abschluss nicht möglich. Datum, Beträge oder bereits bestehende Abschlüsse prüfen.');
                return $this->redirectToRoute('equipment_manager_cash_closing',['date'=>$date]);
            }
            $db=$em->getConnection(); $db->beginTransaction();
            try {
                // Club lock serializes concurrent closings for this club.
                $em->lock($club,LockMode::PESSIMISTIC_WRITE);
                if ($repo->findOneBy(['club'=>$club,'closingDate'=>$day]) || $findLater()) {throw new \RuntimeException('Für dieses Datum besteht bereits ein Abschluss oder ein späterer Abschluss.');}
                $fresh=$this->movements($em,$clubId,$date);
                $expected=$opening+$fresh['in']-$fresh['out'];
                if (($counted!==$expected || !$previous) && $note==='') {throw new \InvalidArgumentException('Anfangsbestand oder Kassendifferenz muss begründet werden.');}
                $em->persist(new EquipmentCashClosing($club,$day,$opening,$fresh['in'],$fresh['out'],$counted,$fresh['hash'],$this->getUser()?->getUserIdentifier()??'unbekannt',$note,$handover));
                $em->flush();$db->commit();
                $this->addFlash('success','Tagesabschluss unveränderlich gespeichert.');
            } catch (\Throwable $e) {
                if ($db->isTransactionActive()) {$db->rollBack();}
                $this->addFlash('danger',$e instanceof \InvalidArgumentException ? $e->getMessage() : 'Abschluss konnte nicht gespeichert werden.');
            }
            return $this->redirectToRoute('equipment_manager_cash_closing',['date'=>$date]);
        }
        return $this->render('equipment_manager/cash_closing.html.twig',[
            'date'=>$date,'existing'=>$existing,'previous'=>$previous,'later'=>$later,'movements'=>$movements,
            'opening'=>$previous? $previous->getCountedCents():null,
            'changed'=>$existing && ($existing->getMovementHash()!==$movements['hash'] || $existing->getCashInCents()!==$movements['in'] || $existing->getCashOutCents()!==$movements['out']),
        ]);
    }
}
