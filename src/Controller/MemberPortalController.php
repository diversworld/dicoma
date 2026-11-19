<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\MemberTank;
use App\Entity\MemberTankCheckBooking;
use App\Entity\Booking;
use App\Entity\ClubEventRegistration;
use App\Entity\InternalMessageRecipient;
use App\Entity\MemberSport;
use App\Entity\Schedule;
use App\Entity\TankCheck;
use App\Entity\User;
use App\Enum\MemberSportStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_MEMBER')]
final class MemberPortalController extends AbstractController
{
    #[Route('/my/bookings', name: 'member_bookings', methods: ['GET'])]
    public function bookings(EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User || $user->getMember() === null) {
            throw $this->createAccessDeniedException('Kein Mitgliedsprofil zugeordnet.');
        }
        return $this->render('member_portal/bookings.html.twig', [
            'bookings' => $em->getRepository(MemberTankCheckBooking::class)->findBy(['member' => $user->getMember()], ['id' => 'DESC']),
        ]);
    }

    #[Route('/my', name: 'member_portal_dashboard', methods: ['GET'])]
    public function dashboard(EntityManagerInterface $em, \App\Service\CompetitionAccess $competitions): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User || ($member = $user->getMember()) === null) {
            throw $this->createAccessDeniedException('Kein Mitgliedsprofil zugeordnet.');
        }
        $tanks = $em->getRepository(MemberTank::class)->findBy(['member' => $member], ['id' => 'DESC']);
        $bookings = $em->getRepository(MemberTankCheckBooking::class)->findBy(['member' => $member], ['id' => 'DESC'], 6);
        $upcoming = $em->createQueryBuilder()->select('c')->from(TankCheck::class, 'c')
            ->where('c.isPublished = true')->andWhere('c.checkDate >= :today')
            ->setParameter('today', new \DateTimeImmutable('today'))
            ->orderBy('c.checkDate', 'ASC')->setMaxResults(3)->getQuery()->getResult();
        $dueCount = 0;
        foreach ($tanks as $tank) {
            if (in_array($tank->getInspectionDueStatus(), ['Überfällig', 'Bald fällig'], true)) {
                ++$dueCount;
            }
        }
        $sports = array_values(array_filter(
            $em->getRepository(MemberSport::class)->findBy(['member' => $member], ['id' => 'ASC']),
            static fn (MemberSport $membership): bool => $membership->getStatus() !== MemberSportStatus::FORMER
                && $membership->getSport()?->isActive() === true,
        ));
        $unreadMessages = (int) $em->getRepository(InternalMessageRecipient::class)->count([
            'member' => $member, 'readAt' => null,
        ]);
        return $this->render('member_portal/dashboard.html.twig', [
            'tankCount' => count($tanks), 'dueCount' => $dueCount,
            'bookings' => $bookings, 'upcoming' => $upcoming,
            'competitionStaff' => $competitions->isStaff(),
            'sports' => $sports,
            'unreadMessages' => $unreadMessages,
        ]);
    }

    #[Route('/my/calendar', name: 'member_personal_calendar', methods: ['GET'])]
    public function personalCalendar(EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        $member = $user instanceof User ? $user->getMember() : null;
        if ($member === null || !$member->isActiveMember()) {
            throw $this->createAccessDeniedException('Aktive Mitgliedschaft erforderlich.');
        }
        $from = new \DateTimeImmutable('today');
        $until = $from->modify('+1 year');
        $items = [];
        $bookings = $em->getRepository(Booking::class)->createQueryBuilder('b')
            ->join('b.schedule', 's')->join('s.courses', 'c')
            ->where('b.member = :member')
            ->andWhere('s.startDate >= :from')
            ->andWhere('s.startDate < :until')
            ->setParameter('member', $member)
            ->setParameter('from', $from)
            ->setParameter('until', $until)
            ->getQuery()->getResult();
        foreach ($bookings as $booking) {
            $schedule = $booking->getSchedule();
            if ($schedule?->getStartDate() === null) {
                continue;
            }
            $time = $schedule->getStartTime()?->format('H:i');
            $items[] = [
                'date' => $schedule->getStartDate()->format('Y-m-d'),
                'time' => $time,
                'title' => (string) ($schedule->getCourses()?->getTitle() ?? $schedule->getTitle()),
                'detail' => $schedule->getLocation() ?: $schedule->getTitle(),
                'type' => 'Kurs / Terminbuchung',
            ];
        }
        $registrations = $em->getRepository(ClubEventRegistration::class)->createQueryBuilder('r')
            ->join('r.event', 'e')
            ->where('r.person = :person')
            ->andWhere('r.status NOT IN (:excluded)')
            ->andWhere('e.startsAt >= :now')
            ->setParameter('person', $member->getPerson())
            ->setParameter('excluded', ['cancelled', 'ineligible'])
            ->setParameter('now', $from)
            ->getQuery()->getResult();
        foreach ($registrations as $registration) {
            $event = $registration->getEvent();
            if ($event->getStartsAt() === null) {
                continue;
            }
            $items[] = [
                'date' => $event->getStartsAt()->format('Y-m-d'),
                'time' => $event->getStartsAt()->format('H:i'),
                'title' => $event->getTitle(),
                'detail' => $event->getLocation(),
                'type' => 'Veranstaltung',
            ];
        }
        $teaching = $em->getRepository(Schedule::class)->createQueryBuilder('s')
            ->where('s.instructor = :member')
            ->andWhere('s.startDate >= :from')
            ->andWhere('s.startDate < :until')
            ->setParameter('member', $member)
            ->setParameter('from', $from)
            ->setParameter('until', $until)
            ->getQuery()->getResult();
        foreach ($teaching as $schedule) {
            if ($schedule->getStartDate() === null) {
                continue;
            }
            $items[] = [
                'date' => $schedule->getStartDate()->format('Y-m-d'),
                'time' => $schedule->getStartTime()?->format('H:i'),
                'title' => (string) $schedule->getTitle(),
                'detail' => $schedule->getLocation() ?: 'Als Ausbilder zugewiesen',
                'type' => 'Ausbildungsauftrag',
            ];
        }
        usort($items, static fn (array $first, array $second): int =>
            strcmp($first['date'].' '.$first['time'], $second['date'].' '.$second['time'])
        );
        return $this->render('member_portal/calendar.html.twig', ['items' => $items]);
    }
}
