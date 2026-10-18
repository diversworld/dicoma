<?php
declare(strict_types=1);

namespace App\Controller;

use App\Entity\MemberTankCheckBooking;
use App\Entity\MemberTankCheckResult;
use App\Entity\MemberTankCheckStatusHistory;
use App\Entity\TankCheck;
use App\Entity\User;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_EQUIPMENT_MANAGER')]
final class EquipmentManagerTuvWorkflowController extends AbstractController
{
    private function clubId(): int
    {
        $user = $this->getUser();
        if (!$user instanceof User || ($club = $user->getMember()?->getClub()) === null || $club->getId() === null) {
            throw $this->createAccessDeniedException('Kein Verein zugeordnet.');
        }
        return (int) $club->getId();
    }

    private function ownedBooking(EntityManagerInterface $em, int $id, int $clubId): MemberTankCheckBooking
    {
        $booking = $em->find(MemberTankCheckBooking::class, $id, LockMode::PESSIMISTIC_WRITE);
        if (!$booking instanceof MemberTankCheckBooking) {
            throw $this->createNotFoundException('TÜV-Anmeldung nicht gefunden.');
        }
        $belongs = $em->createQueryBuilder()->select('b.id')
            ->from(MemberTankCheckBooking::class, 'b')
            ->leftJoin('b.clubTank', 'ct')->leftJoin('b.member', 'm')
            ->where('b.id = :id')
            ->andWhere('(IDENTITY(ct.club) = :clubId OR IDENTITY(m.club) = :clubId)')
            ->setParameter('id', $id)->setParameter('clubId', $clubId)
            ->getQuery()->getOneOrNullResult();
        if ($belongs === null) {
            throw $this->createAccessDeniedException('Die Anmeldung gehört nicht zu deinem Verein.');
        }
        return $booking;
    }

    #[Route('/equipment-manager/tuv/plan', name: 'equipment_manager_tuv_plan', methods: ['GET', 'POST'])]
    public function plan(Request $request, EntityManagerInterface $em): Response
    {
        $this->clubId();
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('tuv_plan', (string) $request->request->get('_token', ''))) {
                throw $this->createAccessDeniedException('Ungültiges CSRF-Token.');
            }
            $rawDate = $request->request->get('date');
            $rawDeadline = $request->request->get('deadline');
            $vendor = trim((string) $request->request->get('vendor', ''));
            $date = is_string($rawDate) ? \DateTimeImmutable::createFromFormat('!Y-m-d', $rawDate) : false;
            $deadline = is_string($rawDeadline) && $rawDeadline !== '' ? \DateTimeImmutable::createFromFormat('!Y-m-d', $rawDeadline) : null;
            if (!$date || $date->format('Y-m-d') !== $rawDate || $date < new \DateTimeImmutable('today')
                || ($deadline === false) || ($deadline !== null && ($deadline > $date || $deadline < new \DateTimeImmutable('today')))
                || mb_strlen($vendor) > 150) {
                $this->addFlash('danger', 'Bitte gültiges Termin- und Anmeldedatum eingeben.');
                return $this->redirectToRoute('equipment_manager_tuv_plan');
            }
            $check = (new TankCheck())->setCheckDate($date)->setVendorName($vendor !== '' ? $vendor : null)->setRegistrationDeadline($deadline);
            // Termine sind im aktuellen Modell global: neue Termine bleiben unveröffentlicht.
            // Nur Administratoren dürfen globale Termine veröffentlichen.
            $check->setIsPublished(false);
            $em->persist($check);
            $em->flush();
            $this->addFlash('success', 'Termin als Entwurf angelegt. Die Administration kann ihn nach Prüfung veröffentlichen.');
            return $this->redirectToRoute('equipment_manager_tuv');
        }
        return $this->render('equipment_manager/tuv_plan.html.twig');
    }

    #[Route('/equipment-manager/tuv/booking/{id}/result', name: 'equipment_manager_tuv_result', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function result(int $id, Request $request, EntityManagerInterface $em): Response
    {
        $clubId = $this->clubId();
        if ($request->isMethod('GET')) {
            $booking = $em->getRepository(MemberTankCheckBooking::class)->find($id);
            if (!$booking) { throw $this->createNotFoundException(); }
            // GET scope check without acquiring a pessimistic lock.
            $scope = $em->createQueryBuilder()->select('b.id')->from(MemberTankCheckBooking::class, 'b')
                ->leftJoin('b.clubTank', 'ct')->leftJoin('b.member', 'm')
                ->where('b.id = :id')->andWhere('(IDENTITY(ct.club) = :clubId OR IDENTITY(m.club) = :clubId)')
                ->setParameter('id', $id)->setParameter('clubId', $clubId)->getQuery()->getOneOrNullResult();
            if (!$scope) { throw $this->createAccessDeniedException(); }
            if ($booking->getStatus() !== 'returned') { throw $this->createAccessDeniedException('Ergebnis erst nach Rückerhalt möglich.'); }
            return $this->render('equipment_manager/tuv_result.html.twig', ['booking' => $booking]);
        }
        if (!$this->isCsrfTokenValid('tuv_result_'.$id, (string) $request->request->get('_token', ''))) {
            throw $this->createAccessDeniedException('Ungültiges CSRF-Token.');
        }
        $result = $request->request->get('result');
        $dateRaw = $request->request->get('date');
        $date = is_string($dateRaw) ? \DateTimeImmutable::createFromFormat('!Y-m-d', $dateRaw) : false;
        $notes = trim((string) $request->request->get('notes', ''));
        if (!in_array($result, ['passed', 'failed'], true) || !$date || $date->format('Y-m-d') !== $dateRaw
            || $date > new \DateTimeImmutable('today') || mb_strlen($notes) > 2000) {
            $this->addFlash('danger', 'Bitte Ergebnis und Prüfdatum kontrollieren.');
            return $this->redirectToRoute('equipment_manager_tuv_result', ['id' => $id]);
        }
        $em->wrapInTransaction(function (EntityManagerInterface $em) use ($id, $clubId, $result, $date, $notes): void {
            $booking = $this->ownedBooking($em, $id, $clubId);
            if ($booking->getStatus() !== 'returned') { throw $this->createAccessDeniedException('Status wurde zwischenzeitlich geändert.'); }
            $record = $em->getRepository(MemberTankCheckResult::class)->findOneBy(['booking' => $booking]);
            if ($record === null) { $record = new MemberTankCheckResult($booking); $em->persist($record); }
            $record->record($result, $date, $notes !== '' ? $notes : null, $this->getUser()->getUserIdentifier());
            if ($result === 'passed') {
                $next = $date->modify('+30 months');
                if (($tank = $booking->getClubTank()) !== null) {
                    $tank->setLastCheckDate($date)->setNextCheckDate($next);
                } elseif (($tank = $booking->getMemberTank()) !== null) {
                    $tank->setNextInspectionAt($next);
                }
            }
            $booking->setStatus('completed');
            $em->persist(new MemberTankCheckStatusHistory($booking, 'returned', 'completed', $this->getUser()->getUserIdentifier()));
        });
        $this->addFlash('success', 'Prüfergebnis gespeichert und Anmeldung abgeschlossen.');
        return $this->redirectToRoute('equipment_manager_tuv');
    }
}
