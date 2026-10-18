<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\MemberTankCheckBooking;
use App\Entity\Tank;
use App\Entity\TankCheck;
use App\Entity\User;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_EQUIPMENT_MANAGER')]
final class EquipmentManagerTuvClubRegistrationController extends AbstractController
{
    #[Route('/equipment-manager/tuv/{id}/club-tanks', name: 'equipment_manager_tuv_club_register', methods: ['GET', 'POST'], requirements: ['id' => '\\d+'])]
    public function register(int $id, Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User || null === ($club = $user->getMember()?->getClub()) || null === $club->getId()) {
            throw $this->createAccessDeniedException('Kein Verein zugeordnet.');
        }
        $check = $em->find(TankCheck::class, $id);
        if (!$check instanceof TankCheck || !$check->isPublished() || $check->getCheckDate() === null || $check->getCheckDate() < new \DateTimeImmutable('today')) {
            throw $this->createNotFoundException('Dieser Prüftermin ist nicht zur Anmeldung verfügbar.');
        }
        $deadline = $check->getRegistrationDeadline();
        if ($deadline !== null && $deadline < new \DateTimeImmutable('today')) {
            throw $this->createAccessDeniedException('Die Anmeldefrist ist abgelaufen.');
        }
        $clubId = $club->getId();
        $tanks = $em->getRepository(Tank::class)->createQueryBuilder('t')
            ->where('IDENTITY(t.club) = :club')->setParameter('club', $clubId)
            ->orderBy('t.inventory', 'ASC')->getQuery()->getResult();
        $booked = $em->getRepository(MemberTankCheckBooking::class)->createQueryBuilder('b')
            ->select('IDENTITY(b.clubTank) AS tankId')
            ->where('b.tankCheck = :check')->andWhere('b.clubTank IS NOT NULL')
            ->setParameter('check', $check)->getQuery()->getScalarResult();
        $bookedIds = array_fill_keys(array_map(static fn (array $r): int => (int) $r['tankId'], $booked), true);
        $available = array_values(array_filter($tanks, static fn (Tank $tank): bool => !isset($bookedIds[$tank->getId()])));

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('tuv_club_register_'.$id, (string) $request->request->get('_token', ''))) {
                throw $this->createAccessDeniedException('Ungültiges CSRF-Token.');
            }
            $selected = $request->request->all('tanks');
            $ids = array_unique(array_filter(array_map('intval', $selected), static fn (int $value): bool => $value > 0));
            $allowed = [];
            foreach ($available as $tank) { $allowed[$tank->getId()] = $tank; }
            if ($ids === [] || count($ids) > 100 || count(array_diff($ids, array_keys($allowed))) > 0) {
                $this->addFlash('danger', 'Bitte gültige, noch nicht angemeldete Vereinsflaschen auswählen.');
                return $this->redirectToRoute('equipment_manager_tuv_club_register', ['id' => $id]);
            }
            try {
                $em->wrapInTransaction(function (EntityManagerInterface $em) use ($ids, $allowed, $check): void {
                    foreach ($ids as $tankId) {
                        $booking = (new MemberTankCheckBooking())->setClubTank($allowed[$tankId])->setTankCheck($check);
                        $em->persist($booking);
                    }
                });
            } catch (UniqueConstraintViolationException) {
                $this->addFlash('warning', 'Mindestens eine Flasche wurde zwischenzeitlich angemeldet. Bitte Auswahl aktualisieren.');
                return $this->redirectToRoute('equipment_manager_tuv_club_register', ['id' => $id]);
            }
            $this->addFlash('success', count($ids).' Vereinsflasche(n) zum TÜV-Termin angemeldet.');
            return $this->redirectToRoute('equipment_manager_tuv');
        }
        return $this->render('equipment_manager/tuv_club_register.html.twig', [
            'check' => $check, 'tanks' => $available, 'clubName' => $club->getName(),
        ]);
    }
}
