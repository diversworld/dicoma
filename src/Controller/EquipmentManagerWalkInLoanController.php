<?php
declare(strict_types=1);

namespace App\Controller;

use App\Entity\{EquipmentBooking,EquipmentBookingItem,EquipmentItem,Regulator,Tank,Member,User};
use App\Service\EquipmentBookingService;
use App\Service\EquipmentAssetAvailability;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request,Response};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_EQUIPMENT_MANAGER')]
final class EquipmentManagerWalkInLoanController extends AbstractController
{
    #[Route('/equipment-manager/loans/direct', name: 'equipment_manager_loans_direct', methods: ['GET','POST'])]
    public function direct(Request $request, EntityManagerInterface $em, EquipmentBookingService $bookingService, EquipmentAssetAvailability $availability): Response
    {
        $user = $this->getUser();
        $club = $user instanceof User ? $user->getMember()?->getClub() : null;
        if (!$club || !$club->getId()) {
            throw $this->createAccessDeniedException('Kein Verein zugeordnet.');
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('equipment_manager_walkin', (string) $request->request->get('_token', ''))) {
                throw $this->createAccessDeniedException('Ungültiges CSRF-Token.');
            }
            $memberId = filter_var($request->request->get('member_id'), FILTER_VALIDATE_INT);
            $member = $memberId ? $em->find(Member::class, $memberId) : null;
            if (!$member || $member->getClub()?->getId() !== $club->getId()) {
                throw $this->createAccessDeniedException('Mitglied gehört nicht zu deinem Verein.');
            }
            $selected = $request->request->all('items');
            if (count($selected) < 1 || count($selected) > 30 || count($selected) !== count(array_unique($selected))) {
                $this->addFlash('danger', 'Bitte 1 bis 30 verschiedene Gegenstände auswählen.');
                return $this->redirectToRoute('equipment_manager_loans_direct');
            }
            $endRaw = $request->request->get('end');
            $end = is_string($endRaw) ? \DateTimeImmutable::createFromFormat('!Y-m-d', $endRaw) : false;
            $start = new \DateTimeImmutable('today');
            if (!$end || $end->format('Y-m-d') !== $endRaw || $end <= $start || $end > $start->modify('+90 days')) {
                $this->addFlash('danger', 'Rückgabe muss in der Zukunft und innerhalb von 90 Tagen liegen.');
                return $this->redirectToRoute('equipment_manager_loans_direct');
            }
            $booking = (new EquipmentBooking())->setMember($member)->setStartsAt($start)->setEndsAt($end)
                ->setNotes('Direktausleihe am Schalter durch Gerätewart');
            foreach ($selected as $key) {
                if (!is_string($key) || !preg_match('/^(item|regulator|tank):([1-9][0-9]*)$/D', $key, $match)) {
                    throw $this->createAccessDeniedException('Ungültiger Inventarschlüssel.');
                }
                $class = match ($match[1]) {
                    'item' => EquipmentItem::class,
                    'regulator' => Regulator::class,
                    'tank' => Tank::class,
                };
                $asset = $em->find($class, (int) $match[2]);
                if (!$asset || $asset->getClub()?->getId() !== $club->getId() ||
                    !$asset->isLendable() || $asset->getConditionStatus() !== 'ready') {
                    $this->addFlash('danger', 'Ein Gegenstand ist nicht verleihbar.');
                    return $this->redirectToRoute('equipment_manager_loans_direct');
                }
                if ($asset instanceof EquipmentItem && $asset->getCategory() && !$asset->getCategory()->isActive()) {
                    $this->addFlash('danger', 'Eine Ausrüstungskategorie ist deaktiviert.');
                    return $this->redirectToRoute('equipment_manager_loans_direct');
                }
                $line = new EquipmentBookingItem();
                match ($match[1]) {
                    'item' => $line->setEquipmentItem($asset),
                    'regulator' => $line->setRegulator($asset),
                    'tank' => $line->setTank($asset),
                };
                $booking->addItem($line);
            }
            try {
                $bookingService->saveNew($booking);
                $this->addFlash('success', 'Direktausleihe #'.$booking->getId().' angelegt. Bitte die Ausgabe jedes Gegenstands unten ausdrücklich bestätigen.');
                return $this->redirectToRoute('equipment_manager_loans', ['status' => 'open'], Response::HTTP_SEE_OTHER);
            } catch (\DomainException $e) {
                $this->addFlash('danger', 'Direktausleihe nicht möglich: '.$e->getMessage());
                return $this->redirectToRoute('equipment_manager_loans_direct', [], Response::HTTP_SEE_OTHER);
            }
        }

        $members = $em->getRepository(Member::class)->findBy(['club' => $club], ['lastname'=>'ASC','firstname'=>'ASC']);
        $items = $em->getRepository(EquipmentItem::class)->findBy(['club'=>$club,'lendable'=>true,'conditionStatus'=>'ready'], ['name'=>'ASC']);
        $regulators = $em->getRepository(Regulator::class)->findBy(['club'=>$club,'lendable'=>true,'conditionStatus'=>'ready'], ['id'=>'ASC']);
        $tanks = $em->getRepository(Tank::class)->findBy(['club'=>$club,'lendable'=>true,'conditionStatus'=>'ready'], ['id'=>'ASC']);
        $start = new \DateTimeImmutable('today');
        $endRaw = $request->query->get('end');
        $end = is_string($endRaw) ? \DateTimeImmutable::createFromFormat('!Y-m-d', $endRaw) : false;
        if (!$end || $end->format('Y-m-d') !== $endRaw || $end <= $start || $end > $start->modify('+90 days')) {
            $end = $start->modify('+1 day');
        }
        $available = [];
        foreach (['item' => $items, 'regulator' => $regulators, 'tank' => $tanks] as $type => $assets) {
            foreach ($assets as $asset) {
                $available[$type.':'.$asset->getId()] = $availability->isAvailablePreview($asset, $club->getId(), $start, $end);
            }
        }
        if ($request->query->get('availability') === '1') {
            return $this->json(['available' => $available, 'end' => $end->format('Y-m-d')]);
        }
        return $this->render('equipment_manager/direct_loan.html.twig', compact('members','items','regulators','tanks','available','end'));

    }
}
