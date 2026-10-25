<?php
declare(strict_types=1);
namespace App\Controller;

use App\Service\EquipmentCashGuard;
use App\Entity\Club;
use App\Entity\EquipmentCashPayout;
use App\Entity\User;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_EQUIPMENT_MANAGER')]
final class EquipmentManagerCashPayoutController extends AbstractController
{
    private function club(EntityManagerInterface $em): Club
    {
        $user = $this->getUser();
        $id = $user instanceof User ? $user->getMember()?->getClub()?->getId() : null;
        if (!$id || !$club = $em->find(Club::class, $id)) { throw $this->createAccessDeniedException('Kein Verein zugeordnet.'); }
        return $club;
    }

    /** Only actual cash receipts count; transfers do not. No assumed opening balance. */
    private function balance(EntityManagerInterface $em, int $clubId): array
    {
        $db = $em->getConnection();
        $tuv = (int) $db->fetchOne("SELECT COALESCE(SUM(r.amount_cents),0) FROM tuv_payment_receipt r INNER JOIN member_tank_check_booking b ON b.id=r.booking_id INNER JOIN member m ON m.id=b.member_id WHERE m.club_id=:club AND r.method='cash'", ['club'=>$clubId]);
        $rental = (int) $db->fetchOne("SELECT COALESCE(SUM(r.amount_cents),0) FROM equipment_rental_receipt r INNER JOIN equipment_booking b ON b.id=r.booking_id INNER JOIN member m ON m.id=b.member_id WHERE m.club_id=:club AND r.method='cash'", ['club'=>$clubId]);
        $fill = (int) $db->fetchOne("SELECT COALESCE(SUM(amount_cents),0) FROM equipment_fill_charge WHERE club_id=:club AND method='cash'", ['club'=>$clubId]);
        $out = (int) $db->fetchOne('SELECT COALESCE(SUM(amount_cents),0) FROM equipment_cash_payout WHERE club_id=:club', ['club'=>$clubId]);
        return ['tuv'=>$tuv, 'rental'=>$rental, 'fill'=>$fill, 'out'=>$out, 'available'=>$tuv+$rental+$fill-$out];
    }

    #[Route('/equipment-manager/cashbook/payouts', name: 'equipment_manager_cash_payouts', methods: ['GET'])]
    public function index(EntityManagerInterface $em, EquipmentCashGuard $guard): Response
    {
        $club = $this->club($em);
        $payouts = $em->getRepository(EquipmentCashPayout::class)->findBy(['club'=>$club], ['paidAt'=>'DESC','id'=>'DESC'], 200);
                $balance=$this->balance($em, (int)$club->getId());
        $balance['available']=$guard->availableCents((int)$club->getId());
        return $this->render('equipment_manager/cash_payouts.html.twig', ['balance'=>$balance, 'payouts'=>$payouts]);
    }

    #[Route('/equipment-manager/cashbook/payouts', name: 'equipment_manager_cash_payout_create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $em, EquipmentCashGuard $guard): Response
    {
        $club = $this->club($em);
        if (!$this->isCsrfTokenValid('cash_payout_'. $club->getId(), (string)$request->request->get('_token', ''))) { throw $this->createAccessDeniedException('Ungültiger CSRF-Token.'); }
        $raw = str_replace(',', '.', trim((string)$request->request->get('amount', '')));
        if (!preg_match('/^\d{1,7}(?:\.\d{1,2})?$/D', $raw)) { $this->addFlash('danger','Ungültiger Betrag.'); return $this->redirectToRoute('equipment_manager_cash_payouts'); }
        [$euros, $cents] = array_pad(explode('.', $raw, 2), 2, '');
        $amount = (int)$euros * 100 + (int)str_pad($cents, 2, '0');
        $category = (string)$request->request->get('category', '');
        $recipient = trim((string)$request->request->get('recipient', ''));
        $purpose = trim((string)$request->request->get('purpose', ''));
        $reference = trim((string)$request->request->get('document_reference', ''));
        if ($amount <= 0 || !in_array($category, ['certification','training_material','equipment_material'], true) || $recipient === '' || $purpose === '' || mb_strlen($recipient)>180 || mb_strlen($purpose)>500 || mb_strlen($reference)>120) {
            $this->addFlash('danger','Bitte alle Angaben prüfen.'); return $this->redirectToRoute('equipment_manager_cash_payouts');
        }
        $db = $em->getConnection();
        $db->beginTransaction();
        try {
            // Serialize payouts for the same club to avoid two simultaneous overdraws.
            $guard->lockAndAssertOpen($club);
            if ($amount > $guard->availableCents((int)$club->getId())) {
                $db->rollBack();
                $this->addFlash('danger','Nicht genügend dokumentierter Bargeldbestand. Auszahlung nicht gespeichert.');
                return $this->redirectToRoute('equipment_manager_cash_payouts');
            }
            $em->persist(new EquipmentCashPayout($club, $category, $recipient, $amount, $this->getUser()?->getUserIdentifier() ?? 'unbekannt', $purpose, $reference));
            $em->flush(); $db->commit();
            $this->addFlash('success','Barauszahlung verbindlich erfasst.');
        } catch (\DomainException $e) {
            if ($db->isTransactionActive()) { $db->rollBack(); }
            $this->addFlash('danger',$e->getMessage());
        } catch (\Throwable $e) {
            if ($db->isTransactionActive()) { $db->rollBack(); }
            throw $e;
        }
        return $this->redirectToRoute('equipment_manager_cash_payouts');
    }
}
