<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\MemberTankCheckBooking;
use App\Entity\TankCheck;
use App\Entity\User;
use App\Entity\MemberTankCheckStatusHistory;
use App\Entity\TuvHandover;
use Doctrine\DBAL\LockMode;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_EQUIPMENT_MANAGER')]
final class EquipmentManagerTuvController extends AbstractController
{
    #[Route('/equipment-manager/tuv', name: 'equipment_manager_tuv', methods: ['GET'])]
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User || null === ($club = $user->getMember()?->getClub()) || null === $club->getId()) {
            throw $this->createAccessDeniedException('Kein Verein zugeordnet.');
        }
        $clubId = $club->getId();
        $status = $request->query->get('status', 'all');
        $allowed = ['all', 'requested', 'received', 'sent', 'returned', 'completed', 'cancelled'];
        if (!is_string($status) || !in_array($status, $allowed, true)) {
            throw $this->createNotFoundException('Ungültiger Filter.');
        }

        // Termine sind im bisherigen Datenmodell global. Nur Termine mit
        // Anmeldungen des eigenen Vereins werden hier angezeigt.
        $qb = $em->createQueryBuilder()
            ->select('b', 'tc', 'mt', 'ct')
            ->from(MemberTankCheckBooking::class, 'b')
            ->join('b.tankCheck', 'tc')
            ->leftJoin('b.memberTank', 'mt')
            ->leftJoin('b.clubTank', 'ct')
            ->leftJoin('b.member', 'm')
            ->where('(IDENTITY(ct.club) = :clubId OR IDENTITY(m.club) = :clubId)')
            ->setParameter('clubId', $clubId)
            ->orderBy('tc.checkDate', 'DESC')
            ->addOrderBy('b.id', 'DESC')
            ->setMaxResults(200);
        if ($status !== 'all') {
            $qb->andWhere('b.status = :status')->setParameter('status', $status);
        }
        $bookings = $qb->getQuery()->getResult();
        // Globale veröffentlichte Termine separat anzeigen, auch wenn noch keine
        // Flasche angemeldet ist. Unveröffentlichte Termine werden nur angezeigt,
        // wenn bereits eine Buchung aus dem eigenen Verein existiert.
        $upcoming = $em->getRepository(TankCheck::class)->createQueryBuilder('t')
            ->where('t.isPublished = :published')
            ->andWhere('t.checkDate >= :today')
            ->setParameter('published', true)
            ->setParameter('today', new \DateTimeImmutable('today'))
            ->orderBy('t.checkDate', 'ASC')
            ->setMaxResults(30)
            ->getQuery()->getResult();
        $groups = [];
        foreach ($bookings as $booking) {
            $check = $booking->getTankCheck();
            if (!$check instanceof TankCheck) {
                continue;
            }
            $key = (string) $check->getId();
            if (!isset($groups[$key])) {
                $groups[$key] = ['check' => $check, 'bookings' => [], 'clubCount' => 0, 'memberCount' => 0];
            }
            $groups[$key]['bookings'][] = $booking;
            if ($booking->getClubTank() !== null) {
                ++$groups[$key]['clubCount'];
            } else {
                ++$groups[$key]['memberCount'];
            }
        }

        $upcomingIds = [];
        foreach ($upcoming as $check) {
            $upcomingIds[(string) $check->getId()] = true;
        }
        return $this->render('equipment_manager/tuv_overview.html.twig', [
            'upcoming' => $upcoming,
            'upcomingIds' => $upcomingIds,
            'clubName' => $club->getName(),
            'groups' => $groups,
            'status' => $status,
            'total' => count($bookings),
            'limited' => count($bookings) === 200,
        ]);
    }

    /**
     * Statuswechsel ausschließlich nach POST, mit CSRF und Vereinsscope.
     * "completed" bleibt der bestehenden Ergebnis-/Abschlusslogik vorbehalten.
     */
    #[Route('/equipment-manager/tuv/booking/{id}/advance', name: 'equipment_manager_tuv_advance', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function advance(int $id, Request $request, EntityManagerInterface $em): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('tuv_advance_'.$id, (string) $request->request->get('_token', ''))) {
            throw $this->createAccessDeniedException('Ungültiges CSRF-Token.');
        }
        $user = $this->getUser();
        if (!$user instanceof User || null === ($club = $user->getMember()?->getClub()) || null === $club->getId()) {
            throw $this->createAccessDeniedException('Kein Verein zugeordnet.');
        }
        $clubId = $club->getId();
        $em->wrapInTransaction(function (EntityManagerInterface $em) use ($id, $clubId, $user): void {
            $booking = $em->find(MemberTankCheckBooking::class, $id, LockMode::PESSIMISTIC_WRITE);
            if (!$booking instanceof MemberTankCheckBooking) {
                throw $this->createNotFoundException('Anmeldung nicht gefunden.');
            }
            $belongs = $em->createQueryBuilder()
                ->select('b.id')->from(MemberTankCheckBooking::class, 'b')
                ->leftJoin('b.clubTank', 'ct')->leftJoin('b.member', 'm')
                ->where('b.id = :id')
                ->andWhere('(IDENTITY(ct.club) = :clubId OR IDENTITY(m.club) = :clubId)')
                ->setParameter('id', $id)->setParameter('clubId', $clubId)
                ->getQuery()->getOneOrNullResult();
            if ($belongs === null) {
                throw $this->createAccessDeniedException('Anmeldung gehört nicht zum eigenen Verein.');
            }
            $next = ['requested' => 'received', 'received' => 'sent', 'sent' => 'returned'];
            $old = $booking->getStatus();
            if (!isset($next[$old])) {
                throw $this->createAccessDeniedException('Für diesen Status ist kein direkter Übergang erlaubt.');
            }
            $new = $next[$old];
            $booking->setStatus($new);
            $actor = $user->getUserIdentifier();
            $em->persist(new MemberTankCheckStatusHistory($booking, $old, $new, $actor));
            $em->persist(new TuvHandover($booking, $new, $actor, null));
        });
        $this->addFlash('success', 'Bearbeitungsstatus aktualisiert.');
        return $this->redirectToRoute('equipment_manager_tuv');
    }
}
