<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Service\ClubCalendar;
use App\Service\CompetitionAccess;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class ClubCalendarController extends AbstractController
{
    #[Route('/admin/calendar', name: 'club_calendar', methods: ['GET'])]
    public function index(Request $request, ClubCalendar $calendar, CompetitionAccess $access): Response
    {
        $access->require($access->isStaff());
        $value = $request->query->get('month', date('Y-m'));
        if (!is_string($value) || !preg_match('/^\d{4}-(0[1-9]|1[0-2])$/D', $value)) { throw $this->createNotFoundException('Ungültiger Monat.'); }
        $from = \DateTimeImmutable::createFromFormat('!Y-m-d', $value . '-01');
        if (!$from || $from->format('Y-m') !== $value) { throw $this->createNotFoundException('Ungültiger Monat.'); }
        return $this->render('competition/calendar.html.twig', ['month' => $from,
            'events' => $calendar->events($from, $from->modify('first day of next month'))]);
    }
}
