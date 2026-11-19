<?php

declare(strict_types=1);

namespace App\Controller\Frontend;

use App\Controller\Admin\DashboardController;
use App\Http\EasyAdminRequest;
use App\Service\ClubCalendar;
use App\Service\CompetitionAccess;
use App\Service\BarDutyAccess;
use App\Entity\Sport;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class ClubCalendarController extends AbstractController
{
    #[Route('/calendar', name: 'member_calendar', methods: ['GET'])]
    #[Route('/admin/calendar', name: 'club_calendar', defaults: DashboardController::ROUTE_DEFAULTS, methods: ['GET'])]
    public function index(Request $request, ClubCalendar $calendar, CompetitionAccess $access, BarDutyAccess $barAccess, EntityManagerInterface $em): Response
    {
        $access->require($access->isStaff() || ($this->getUser() instanceof \App\Entity\User
            && $this->getUser()->getMember()?->getClub() !== null
            && ($barAccess->isManager() || $this->getUser()->getMember()->isActiveMember())));
        $value = $request->query->get('month', date('Y-m'));
        if (!is_string($value) || !preg_match('/^\d{4}-(0[1-9]|1[0-2])$/D', $value)) { throw $this->createNotFoundException('Ungültiger Monat.'); }
        $from = \DateTimeImmutable::createFromFormat('!Y-m-d', $value . '-01');
        if (!$from || $from->format('Y-m') !== $value) { throw $this->createNotFoundException('Ungültiger Monat.'); }
        $user = $this->getUser();
        $club = $user instanceof User ? $user->getMember()?->getClub() : null;
        $sports = $club === null ? [] : $em->getRepository(Sport::class)->findBy(['club' => $club, 'active' => true], ['name' => 'ASC']);
        $divisionId = filter_var($request->query->get('division'), FILTER_VALIDATE_INT);
        if ($divisionId !== false) {
            $selected = array_filter($sports, static fn (Sport $sport): bool => $sport->getId() === $divisionId);
            if ($selected === []) { throw $this->createNotFoundException('Sparte nicht verfügbar.'); }
        } else {
            $divisionId = null;
        }
        return $this->render('competition/calendar.html.twig', ['month' => $from, 'competitionStaff' => $access->isStaff(),
            'sports' => $sports, 'divisionId' => $divisionId,
            'events' => $calendar->events($from, $from->modify('first day of next month'), EasyAdminRequest::matches($request), $divisionId)]);
    }
}
