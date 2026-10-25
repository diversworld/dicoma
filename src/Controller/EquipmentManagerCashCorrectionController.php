<?php
declare(strict_types=1);
namespace App\Controller;

use App\Entity\Club;
use App\Entity\User;
use App\Service\EquipmentCashGuard;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_EQUIPMENT_MANAGER')]
final class EquipmentManagerCashCorrectionController extends AbstractController
{
    #[Route('/equipment-manager/cashbook/corrections', name: 'equipment_manager_cash_corrections', methods: ['GET','POST'])]
    public function index(Request $request, EntityManagerInterface $em, EquipmentCashGuard $guard): Response
    {
        $user=$this->getUser();
        $clubId=$user instanceof User ? $user->getMember()?->getClub()?->getId() : null;
        if (!$clubId) { throw $this->createAccessDeniedException('Kein Verein zugeordnet.'); }
        $db=$em->getConnection();
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('cash_correction_'.$clubId, (string)$request->request->get('_token',''))) {throw $this->createAccessDeniedException('CSRF-Prüfung fehlgeschlagen.');}
            $kind=(string)$request->request->get('kind','');
            $id=filter_var($request->request->get('reference_id'), FILTER_VALIDATE_INT);
            $reason=trim((string)$request->request->get('reason',''));
            if (!in_array($kind,['tuv','rental','fill','payout'],true) || !$id || $id<1 || mb_strlen($reason)<10 || mb_strlen($reason)>500) {
                $this->addFlash('danger','Art, Beleg-ID und Begründung (10–500 Zeichen) prüfen.');
                return $this->redirectToRoute('equipment_manager_cash_corrections');
            }
            $db->beginTransaction();
            try {
                $guard->lockAndAssertOpen($em->getReference(Club::class,$clubId));
                if ($kind==='payout') {
                    $original=$db->fetchAssociative('SELECT p.amount_cents FROM equipment_cash_payout p WHERE p.id=:id AND p.club_id=:club', ['id'=>$id,'club'=>$clubId]);
                    $direction='in';
                } elseif ($kind==='fill') {
                    $original=$db->fetchAssociative("SELECT amount_cents FROM equipment_fill_charge WHERE id=:id AND club_id=:club AND method='cash'", ['id'=>$id,'club'=>$clubId]);
                    $direction='out';
                } else {
                    $table=$kind==='tuv'?'tuv_payment_receipt':'equipment_rental_receipt';
                    $booking=$kind==='tuv'?'member_tank_check_booking':'equipment_booking';
                    $original=$db->fetchAssociative("SELECT r.amount_cents FROM $table r JOIN $booking b ON b.id=r.booking_id JOIN member m ON m.id=b.member_id WHERE r.id=:id AND m.club_id=:club AND r.method='cash'",['id'=>$id,'club'=>$clubId]);
                    $direction='out';
                }
                if (!$original) {throw new \DomainException('Barbeleg nicht gefunden oder gehört nicht zu deinem Verein.');}
                if ($db->fetchOne('SELECT id FROM equipment_cash_correction WHERE club_id=:club AND source_type=:kind AND source_id=:id',['club'=>$clubId,'kind'=>$kind,'id'=>$id])) {throw new \DomainException('Dieser Beleg wurde bereits korrigiert.');}
                $amount=(int)$original['amount_cents'];
                if ($direction==='out' && $guard->availableCents($clubId)<$amount) {throw new \DomainException('Nicht genug Bargeld für die Rückzahlung vorhanden.');}
                $db->insert('equipment_cash_correction',['club_id'=>$clubId,'source_type'=>$kind,'source_id'=>$id,'direction'=>$direction,'amount_cents'=>$amount,'reason'=>$reason,'created_by'=>mb_substr($user->getUserIdentifier(),0,180),'created_at'=>(new \DateTimeImmutable())->format('Y-m-d H:i:s')]);
                $db->commit();
                $this->addFlash('success','Korrektur als eigene Barbewegung dokumentiert. Ursprungsbeleg bleibt erhalten.');
            } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException|\DomainException $e) {
                if ($db->isTransactionActive()) {$db->rollBack();}
                $this->addFlash('danger',$e instanceof \DomainException?$e->getMessage():'Dieser Beleg wurde bereits korrigiert.');
            } catch (\Throwable $e) {if ($db->isTransactionActive()) {$db->rollBack();}throw $e;}
            return $this->redirectToRoute('equipment_manager_cash_corrections');
        }
        $rows=$db->fetchAllAssociative('SELECT * FROM equipment_cash_correction WHERE club_id=:club ORDER BY created_at DESC,id DESC LIMIT 200',['club'=>$clubId]);
        return $this->render('equipment_manager/cash_corrections.html.twig',['rows'=>$rows,'clubId'=>$clubId]);
    }
}
