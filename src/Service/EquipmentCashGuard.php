<?php
declare(strict_types=1);
namespace App\Service;

use App\Entity\Club;
use App\Entity\EquipmentCashClosing;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

/** Serializes all cash mutations with the club row and forbids backdating into closed periods. */
final class EquipmentCashGuard
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    /** Must be called within an active database transaction, before writing. */
    public function lockAndAssertOpen(Club $club): void
    {
        if (!$this->em->getConnection()->isTransactionActive()) {
            throw new \LogicException('Kassenpruefung erfordert eine Transaktion.');
        }
        $this->em->lock($club, LockMode::PESSIMISTIC_WRITE);
        $last = $this->em->getRepository(EquipmentCashClosing::class)->createQueryBuilder('c')
            ->where('c.club = :club')->setParameter('club', $club)
            ->orderBy('c.closingDate', 'DESC')->setMaxResults(1)->getQuery()->getOneOrNullResult();
        if ($last !== null && $last->getClosingDate()->format('Y-m-d') >= (new \DateTimeImmutable('today'))->format('Y-m-d')) {
            throw new \DomainException('Der heutige Kassentag ist bereits abgeschlossen. Neue Buchungen sind gesperrt.');
        }
    }

    /** Latest physically counted balance plus later cash movements, in cents. */
    public function availableCents(int $clubId): int
    {
        $db=$this->em->getConnection();
        $last=$db->fetchAssociative('SELECT closing_date, counted_cents FROM equipment_cash_closing WHERE club_id=:club ORDER BY closing_date DESC LIMIT 1', ['club'=>$clubId]);
        $after=$last ? $last['closing_date'].' 23:59:59' : null;
        $filter=$after ? ' AND r.collected_at > :after' : '';
        $args=['club'=>$clubId]; if ($after) {$args['after']=$after;}
        $tuv=(int)$db->fetchOne("SELECT COALESCE(SUM(r.amount_cents),0) FROM tuv_payment_receipt r INNER JOIN member_tank_check_booking b ON b.id=r.booking_id INNER JOIN member m ON m.id=b.member_id WHERE m.club_id=:club AND r.method='cash'".$filter,$args);
        $rental=(int)$db->fetchOne("SELECT COALESCE(SUM(r.amount_cents),0) FROM equipment_rental_receipt r INNER JOIN equipment_booking b ON b.id=r.booking_id INNER JOIN member m ON m.id=b.member_id WHERE m.club_id=:club AND r.method='cash'".$filter,$args);
        $fill=(int)$db->fetchOne("SELECT COALESCE(SUM(r.amount_cents),0) FROM equipment_fill_charge r WHERE r.club_id=:club AND r.method='cash'".$filter,$args);
        $pArgs=['club'=>$clubId];$pFilter='';if ($after) {$pFilter=' AND paid_at > :after';$pArgs['after']=$after;}
        $out=(int)$db->fetchOne('SELECT COALESCE(SUM(amount_cents),0) FROM equipment_cash_payout WHERE club_id=:club'.$pFilter,$pArgs);
        $cArgs=['club'=>$clubId];$cFilter='';if ($after) {$cFilter=' AND created_at > :after';$cArgs['after']=$after;}
        $corrections=$db->fetchAllAssociative('SELECT direction,amount_cents FROM equipment_cash_correction WHERE club_id=:club'.$cFilter,$cArgs);
        $net=0;foreach ($corrections as $correction) {$net+=($correction['direction']==='in'?1:-1)*(int)$correction['amount_cents'];}
        return (int)($last['counted_cents']??0)+$tuv+$rental+$fill-$out+$net;
    }
}
