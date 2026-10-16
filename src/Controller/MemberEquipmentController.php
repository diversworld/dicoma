<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Member;
use App\Entity\MemberTank;
use App\Entity\MemberTankCheckBooking;
use App\Entity\TankCheck;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_MEMBER')]
final class MemberEquipmentController extends AbstractController
{
    private const VOLUMES = ['3', '5', '7', '8', '10', '12', '15', '18', '20', '40 cuft', '80 cuft'];

    private function member(): Member
    {
        $user = $this->getUser();
        if (!$user instanceof User || null === $user->getMember()) {
            throw $this->createAccessDeniedException('Kein Mitgliedsprofil zugeordnet.');
        }
        return $user->getMember();
    }

    #[Route('/my/equipment', name: 'member_equipment_index', methods: ['GET'])]
    public function index(EntityManagerInterface $em): Response
    {
        $member = $this->member();
        $tanks = $em->getRepository(MemberTank::class)->findBy(['member' => $member], ['id' => 'DESC']);
        $checks = $em->createQueryBuilder()->select('c')->from(TankCheck::class, 'c')
            ->where('c.checkDate >= :today')->andWhere('c.isPublished = true')
            ->setParameter('today', new \DateTime('today'))
            ->orderBy('c.checkDate', 'ASC')->setMaxResults(30)->getQuery()->getResult();
        $bookings = $em->getRepository(MemberTankCheckBooking::class)->findBy(['member' => $member], ['id' => 'DESC'], 100);
        return $this->render('member_equipment/index.html.twig', compact('tanks', 'checks', 'bookings'));
    }

    /** @return array{label:string,serial:string,manufacturer:?string,volume:string,waterCapacity:string}|null */
    private function tankInput(Request $request): ?array
    {
        $label = trim((string) $request->request->get('label', ''));
        $serial = trim((string) $request->request->get('serial', ''));
        $manufacturer = trim((string) $request->request->get('manufacturer', ''));
        $volume = trim((string) $request->request->get('volume', ''));
        $raw = str_replace(',', '.', trim((string) $request->request->get('water_capacity_liters', '')));

        if (mb_strlen($label) < 2 || mb_strlen($label) > 100
            || mb_strlen($serial) < 2 || mb_strlen($serial) > 120
            || mb_strlen($manufacturer) > 120 || !in_array($volume, self::VOLUMES, true)) {
            $this->addFlash('danger', 'Bitte Bezeichnung, Seriennummer und Flaschengröße gültig eingeben.');
            return null;
        }
        if (!preg_match('/^\d{1,2}(?:\.\d{1,2})?$/D', $raw)
            || (float) $raw < 0.01 || (float) $raw > 50) {
            $this->addFlash('danger', 'Bitte einen Wasserinhalt von 0,01 bis 50,00 Litern mit höchstens zwei Nachkommastellen eingeben.');
            return null;
        }
        return [
            'label' => $label,
            'serial' => $serial,
            'manufacturer' => $manufacturer !== '' ? $manufacturer : null,
            'volume' => $volume,
            'waterCapacity' => number_format((float) $raw, 2, '.', ''),
        ];
    }

    private function applyTankInput(MemberTank $tank, array $data): void
    {
        $tank->setLabel($data['label'])
            ->setSerialNumber($data['serial'])
            ->setManufacturer($data['manufacturer'])
            ->setVolume($data['volume'])
            ->setWaterCapacityLiters($data['waterCapacity']);
    }

    #[Route('/my/equipment/tanks', name: 'member_equipment_tank_create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('member_tank_create', (string) $request->request->get('_token', ''))) {
            throw $this->createAccessDeniedException('Ungültiger CSRF-Token.');
        }
        $data = $this->tankInput($request);
        if ($data === null) {
            return $this->redirectToRoute('member_equipment_index', [], Response::HTTP_SEE_OTHER);
        }
        $tank = (new MemberTank())->setMember($this->member());
        $this->applyTankInput($tank, $data);
        $em->persist($tank);
        $em->flush();
        $this->addFlash('success', 'Private Flasche gespeichert.');
        return $this->redirectToRoute('member_equipment_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/my/equipment/tanks/{id}/edit', name: 'member_equipment_tank_edit', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function edit(int $id, EntityManagerInterface $em): Response
    {
        $tank = $em->getRepository(MemberTank::class)->findOneBy(['id' => $id, 'member' => $this->member()]);
        if ($tank === null) {
            throw $this->createNotFoundException('Flasche nicht gefunden.');
        }
        return $this->render('member_equipment/edit_tank.html.twig', ['tank' => $tank]);
    }

    #[Route('/my/equipment/tanks/{id}/edit', name: 'member_equipment_tank_update', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function update(int $id, Request $request, EntityManagerInterface $em): Response
    {
        $tank = $em->getRepository(MemberTank::class)->findOneBy(['id' => $id, 'member' => $this->member()]);
        if ($tank === null) {
            throw $this->createNotFoundException('Flasche nicht gefunden.');
        }
        if (!$this->isCsrfTokenValid('member_tank_edit_' . $id, (string) $request->request->get('_token', ''))) {
            throw $this->createAccessDeniedException('Ungültiger CSRF-Token.');
        }
        $data = $this->tankInput($request);
        if ($data === null) {
            return $this->redirectToRoute('member_equipment_tank_edit', ['id' => $id], Response::HTTP_SEE_OTHER);
        }
        $this->applyTankInput($tank, $data);
        $em->flush();
        $this->addFlash('success', 'Flasche aktualisiert.');
        return $this->redirectToRoute('member_equipment_index', [], Response::HTTP_SEE_OTHER);
    }
}
