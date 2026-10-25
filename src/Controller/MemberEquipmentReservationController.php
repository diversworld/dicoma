<?php
declare(strict_types=1);
namespace App\Controller;
use App\Entity\{EquipmentItem,EquipmentCategory,Regulator,Tank,EquipmentBooking,EquipmentBookingItem,EquipmentReservation,User};
use App\Service\EquipmentBookingService;
use App\Service\EquipmentBookingWorkflow;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request,Response};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_MEMBER')]
final class MemberEquipmentReservationController extends AbstractController
{
    #[Route('/my/reservations', name: 'member_reservations', methods: ['GET','POST'])]
    public function index(Request $request, EntityManagerInterface $em, EquipmentBookingService $service): Response
    {
        $user=$this->getUser();
        if (!$user instanceof User || ($member=$user->getMember())===null || ($club=$member->getClub())===null || $club->getId()===null) {
            throw $this->createAccessDeniedException('Kein Vereinsmitglied.');
        }
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('member_equipment_reserve', (string)$request->request->get('_token',''))) throw $this->createAccessDeniedException('CSRF-Token ungültig.');
            $selected=$request->request->all('items');
            $selected=array_values(array_unique(array_filter($selected, 'is_string')));
            $startRaw=$request->request->get('start'); $endRaw=$request->request->get('end');
            $start=is_string($startRaw) ? \DateTimeImmutable::createFromFormat('!Y-m-d',$startRaw) : false;
            $end=is_string($endRaw) ? \DateTimeImmutable::createFromFormat('!Y-m-d',$endRaw) : false;
            if (!$selected || count($selected)>30 || !$start || !$end || $start->format('Y-m-d')!==$startRaw || $end->format('Y-m-d')!==$endRaw || $start<new \DateTimeImmutable('today') || $end<=$start || $end>$start->modify('+90 days')) {
                $this->addFlash('danger','Bitte Artikel und einen gültigen Zeitraum auswählen (maximal 90 Tage).');
                return $this->redirectToRoute('member_reservations');
            }
            $booking=(new EquipmentBooking())->setMember($member)->setStartsAt($start)->setEndsAt($end);
            foreach ($selected as $key) {
                if (!preg_match('/^(item|regulator|tank):([1-9][0-9]*)$/D',$key,$matches)) throw $this->createAccessDeniedException('Ungültige Auswahl.');
                $class=match($matches[1]) {'item'=>EquipmentItem::class,'regulator'=>Regulator::class,'tank'=>Tank::class};
                $asset=$em->find($class,(int)$matches[2]);
                if (!$asset || $asset->getClub()?->getId()!==$club->getId() || !$asset->isLendable() || $asset->getConditionStatus()!=='ready') throw $this->createAccessDeniedException('Artikel nicht verfügbar.');
                if ($asset instanceof EquipmentItem && $asset->getCategory() !== null && !$asset->getCategory()->isActive()) {
                    $this->addFlash('danger', 'Mindestens ein vorgemerkter Artikel gehört inzwischen zu einer deaktivierten Ausrüstungskategorie. Bitte die Auswahl aktualisieren.');
                    return $this->redirectToRoute('member_reservations', [], Response::HTTP_SEE_OTHER);
                }
                $line=new EquipmentBookingItem();
                match ($matches[1]) {'item'=>$line->setEquipmentItem($asset),'regulator'=>$line->setRegulator($asset),'tank'=>$line->setTank($asset)};
                $booking->addItem($line);
            }
            try { $service->saveNew($booking); $this->addFlash('success','Reservierung #'.$booking->getId().' erfolgreich gespeichert: '.count($selected).' Artikel vom '.$start->format('d.m.Y').' bis '.$end->format('d.m.Y').'. Sie ist sofort unter „Meine Reservierungen“ sichtbar.'); }
            catch (\DomainException $e) { $this->addFlash('danger','Reservierung nicht gespeichert: '.$e->getMessage()); }
            return $this->redirect($this->generateUrl('member_reservations').'#meine-reservierungen',Response::HTTP_SEE_OTHER);
        }
        $items=$em->getRepository(EquipmentItem::class)->createQueryBuilder('i')->leftJoin('i.category','c')->addSelect('c')->leftJoin('i.manufacturer','mf')->addSelect('mf')->leftJoin('i.size','sz')->addSelect('sz')->where('i.club = :club')->andWhere('i.lendable = true')->andWhere('i.conditionStatus = :ready')->andWhere('(c.id IS NULL OR c.active = :active)')->setParameter('active', true)->setParameter('club',$club)->setParameter('ready','ready')->orderBy('c.name','ASC')->addOrderBy('i.name','ASC')->getQuery()->getResult();
        $regulators=$em->getRepository(Regulator::class)->findBy(['club'=>$club,'lendable'=>true,'conditionStatus'=>'ready'],['id'=>'ASC']);
        $tanks=$em->getRepository(Tank::class)->findBy(['club'=>$club,'lendable'=>true,'conditionStatus'=>'ready'],['id'=>'ASC']);
        $bookings=$em->getRepository(EquipmentBooking::class)->createQueryBuilder('b')->leftJoin('b.items','bi')->addSelect('bi')->where('b.member = :member')->setParameter('member',$member)->orderBy('b.createdAt','DESC')->addOrderBy('b.id','DESC')->setMaxResults(100)->getQuery()->getResult();
        $legacy=$em->getRepository(EquipmentReservation::class)->findBy(['member'=>$member],['startsAt'=>'DESC'],100);
        return $this->render('member_reservation/index.html.twig',compact('items','regulators','tanks','bookings','legacy'));
    }

    #[Route('/my/reservations/{id}/cancel', name: 'member_reservation_cancel', methods: ['POST'], requirements: ['id'=>'\d+'])]
    public function cancel(int $id, Request $request, EntityManagerInterface $em, EquipmentBookingWorkflow $workflow): Response
    {
        $user=$this->getUser(); $member=$user instanceof User ? $user->getMember() : null;
        if (!$member || !$this->isCsrfTokenValid('member_reservation_cancel_'.$id,(string)$request->request->get('_token',''))) throw $this->createAccessDeniedException();
        $booking=$em->getRepository(EquipmentBooking::class)->find($id);
        if (!$booking || $booking->getMember()?->getId()!==$member->getId()) throw $this->createNotFoundException();
        try {
            $workflow->cancel($id, $member->getClub()->getId(), $user->getUserIdentifier(), 'Vom Mitglied storniert', $member->getId());
            $this->addFlash('success', 'Buchung storniert.');
        } catch (\DomainException $e) { $this->addFlash('danger', $e->getMessage()); }
        return $this->redirectToRoute('member_reservations',[],Response::HTTP_SEE_OTHER);
    }
}
