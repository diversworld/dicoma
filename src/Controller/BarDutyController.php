<?php

declare(strict_types=1);
namespace App\Controller;

use App\Entity\{BarCashSession, BarDutyAssignment, BarDutyExchange, BarDutyNotification, BarDutyPolicy, BarShift, BarShiftDefinition, Member};
use App\Service\{BarCashWorkflow, BarDutyAccess, BarDutyAccounts, BarDutyWorkflow};
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response, StreamedResponse};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class BarDutyController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $em, private readonly BarDutyAccess $access, private readonly \Symfony\Component\HttpFoundation\RequestStack $requests) {}

    private function backend(): bool
    {
        return (bool) $this->requests->getCurrentRequest()?->attributes->get('routeCreatedByEasyAdmin');
    }

    private function route(string $name): string { return ($this->backend() ? 'admin_portal_' : '').$name; }

    private function requireManagement(): void
    {
        if (!$this->backend()) { throw $this->createAccessDeniedException('Verwaltung ausschließlich im Backend.'); }
        $this->access->requireManager();
    }

    #[Route('/bar-duty', name: 'bar_duty', methods: ['GET'])]
    public function index(Request $request, BarDutyAccounts $accounts): Response
    {
        $club = $this->access->club();
        $raw = $request->query->getString('month', date('Y-m'));
        try { $month = BarDutyWorkflow::date($raw.'-01'); } catch (\DomainException) { throw $this->createNotFoundException('Ungültiger Monat.'); }
        $from = $month->modify('monday this week');
        $until = $month->modify('last day of this month')->modify('sunday this week')->modify('+1 day');
        $shifts = $this->em->getRepository(BarShift::class)->createQueryBuilder('s')
            ->where('s.club = :club AND s.startsAt >= :start AND s.startsAt < :end')->setParameter('club', $club)
            ->setParameter('start', $from)->setParameter('end', $until)->orderBy('s.startsAt', 'ASC')->getQuery()->getResult();
        $roster = []; $byDay = [];
        foreach ($shifts as $shift) { $roster[$shift->getId()] = []; $byDay[$shift->getStartsAt()->format('Y-m-d')][] = $shift; }
        if ($shifts !== []) {
            $assignments = $this->em->getRepository(BarDutyAssignment::class)->createQueryBuilder('a')
                ->join('a.member', 'm')->addSelect('m')->join('m.person', 'p')->addSelect('p')
                ->where('a.shift IN (:shifts) AND a.status = :active')->setParameter('shifts', $shifts)->setParameter('active', 'active')->orderBy('a.id', 'ASC')->getQuery()->getResult();
            foreach ($assignments as $assignment) { $roster[$assignment->getShift()->getId()][] = $assignment; }
        }
        $days = [];
        for ($day = $from; $day < $until; $day = $day->modify('+1 day')) {
            $days[] = ['date' => $day, 'currentMonth' => $day->format('Y-m') === $month->format('Y-m'), 'shifts' => $byDay[$day->format('Y-m-d')] ?? []];
        }
        return $this->render('bar_duty/index.html.twig', [
            'month' => $month, 'weeks' => array_chunk($days, 7), 'roster' => $roster, 'manager' => $this->backend() && $this->access->isManager(),
            'account' => $accounts->account($this->access->member(), (int) $month->format('Y')),
            'notifications' => $this->em->getRepository(BarDutyNotification::class)->findBy(['member' => $this->access->member()], ['id' => 'DESC'], 100),
            'exchanges' => $this->exchanges(),
        ]);
    }

    #[Route('/bar-duty/cash', name: 'bar_duty_cash', methods: ['GET'])]
    public function cash(): Response
    {
        $member = $this->access->member();
        if (!$member->isActiveMember()) { throw $this->createAccessDeniedException('Aktive Mitgliedschaft erforderlich.'); }
        return $this->render('bar_duty/cash.html.twig', [
            'manager' => false,
            'assignments' => $this->em->getRepository(BarDutyAssignment::class)->createQueryBuilder('a')
                ->join('a.shift', 's')->addSelect('s')
                ->where('a.member = :member AND a.status = :active AND a.attendance != :absent AND s.status != :cancelled')
                ->setParameter('member', $member)->setParameter('active', 'active')->setParameter('absent', 'absent')
                ->setParameter('cancelled', 'cancelled')->orderBy('s.startsAt', 'DESC')->getQuery()->getResult(),
        ]);
    }

    #[Route('/bar-duty/plan', name: 'bar_duty_plan', methods: ['GET'])]
    public function plan(Request $request): Response
    {
        $this->requireManagement();
        try { $date = BarDutyWorkflow::date($request->query->getString('date', date('Y-m-d'))); }
        catch (\DomainException) { throw $this->createNotFoundException('Ungültiger Veranstaltungstag.'); }
        return $this->render('bar_duty/plan.html.twig', ['manager' => true, 'date' => $date]);
    }

    #[Route('/bar-duty/settings', name: 'bar_duty_settings', methods: ['GET'])]
    public function settings(Request $request): Response
    {
        $this->requireManagement(); $year = $this->year($request);
        return $this->render('bar_duty/settings.html.twig', [
            'manager' => true, 'year' => $year, 'policy' => $this->em->getRepository(BarDutyPolicy::class)->findOneBy(['club' => $this->access->club(), 'year' => $year]),
            'definitions' => $this->em->getRepository(BarShiftDefinition::class)->findBy(['club' => $this->access->club()], ['id' => 'DESC']),
            'members' => $this->members(),
        ]);
    }

    #[Route('/bar-duty/report', name: 'bar_duty_report', methods: ['GET'])]
    public function report(Request $request, BarDutyAccounts $accounts): Response
    {
        $this->requireManagement(); $year = $this->year($request);
        $sessions = $this->em->getRepository(BarCashSession::class)->createQueryBuilder('c')->join('c.shift', 's')->addSelect('s')
            ->where('s.club = :club AND s.startsAt >= :start AND s.startsAt < :end')->setParameter('club', $this->access->club())
            ->setParameter('start', new \DateTimeImmutable($year.'-01-01'))->setParameter('end', new \DateTimeImmutable(($year + 1).'-01-01'))
            ->orderBy('s.startsAt', 'ASC')->getQuery()->getResult();
        return $this->render('bar_duty/report.html.twig', ['manager' => true, 'year' => $year, 'accounts' => $accounts->report($this->access->club(), $year), 'cashSessions' => $sessions]);
    }

    #[Route('/bar-duty/report.csv', name: 'bar_duty_report_csv', methods: ['GET'])]
    public function reportCsv(Request $request, BarDutyAccounts $accounts): Response
    {
        $this->requireManagement(); $year = $this->year($request);
        $rows = [['Mitglied-ID', 'Mitglied', 'Jahr', 'Pflichtminuten', 'Bestätigte Minuten', 'Geplante Minuten', 'Offene Minuten', 'Saldo Minuten', 'Abwesende Dienste']];
        foreach ($accounts->report($this->access->club(), $year) as $row) {
            $rows[] = [$row['member']->getId(), (string) $row['member'], $year, $row['required'] ?? 'Nicht konfiguriert', $row['earned'], $row['planned'], $row['remaining'] ?? '', $row['balance'] ?? '', $row['absent']];
        }
        return $this->csv($rows, 'thekendienst-stunden-'.$year.'.csv');
    }

    #[Route('/bar-duty/{id}', name: 'bar_duty_shift', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function detail(int $id, BarCashWorkflow $cash): Response
    {
        $shift = $this->em->find(BarShift::class, $id);
        if (!$shift || $shift->getClub()->getId() !== $this->access->club()->getId()) { throw $this->createNotFoundException(); }
        $manager = $this->backend() && $this->access->isManager();
        $cashAccess = $cash->canUse($shift, $this->backend());
        $session = $cashAccess ? $this->em->getRepository(BarCashSession::class)->findOneBy(['shift' => $shift]) : null;
        $counterparts = $this->em->getRepository(BarDutyAssignment::class)->createQueryBuilder('a')->join('a.shift', 's')->addSelect('s')
            ->where('s.club = :club AND s.status = :open AND s.startsAt > :now AND a.status = :active')
            ->setParameter('club', $this->access->club())->setParameter('open', 'open')->setParameter('active', 'active')->setParameter('now', new \DateTimeImmutable())
            ->orderBy('s.startsAt', 'ASC')->getQuery()->getResult();
        return $this->render('bar_duty/shift.html.twig', [
            'shift' => $shift, 'manager' => $manager, 'cashAccess' => $cashAccess, 'members' => $this->members(true), 'counterparts' => $counterparts,
            'assignments' => $this->em->getRepository(BarDutyAssignment::class)->findBy(['shift' => $shift], ['id' => 'ASC']),
            'session' => $session, 'cashEntries' => $session ? $cash->entries($session) : [], 'cashBalance' => $session ? $cash->balance($session) : 0,
        ]);
    }

    #[Route('/bar-duty/cash/{id}.csv', name: 'bar_duty_cash_csv', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function cashCsv(int $id, BarCashWorkflow $cash): Response
    {
        $session = $this->em->find(BarCashSession::class, $id);
        if (!$session || $session->getShift()->getClub()->getId() !== $this->access->club()->getId()) { throw $this->createNotFoundException(); }
        $cash->requireAccess($session->getShift(), $this->backend());
        $rows = [['Thekenkasse', $id, 'Schicht', $session->getShift()->getId()], ['Anfangsbestand Cent', $session->getOpeningCents()],
            ['Beleg-ID', 'Zeit', 'Betrag Cent', 'Grund', 'Referenz', 'Bearbeiter', 'Korrektur von']];
        foreach ($cash->entries($session) as $entry) {
            $rows[] = [$entry->getId(), $entry->getCreatedAt()->format(DATE_ATOM), $entry->getAmountCents(), $entry->getReason(), $entry->getReference(), $entry->getCreatedBy(), $entry->getReversalOf()?->getId() ?? ''];
        }
        $rows[] = ['Sollbestand Cent', $cash->balance($session)];
        $rows[] = ['Istbestand Cent', $session->getCountedCents() ?? 'Offen'];
        $rows[] = ['Abschluss', $session->getClosedAt()?->format(DATE_ATOM) ?? 'Offen', $session->getClosedBy() ?? '', $session->getClosingNote() ?? ''];
        $rows[] = ['Bewegungshash', $session->getMovementHash() ?? ''];
        return $this->csv($rows, 'thekenkasse-'.$id.'.csv');
    }

    #[Route('/bar-duty/actions/{operation}/{id}', name: 'bar_duty_action', requirements: ['id' => '\d+', 'operation' => 'plan|define|deactivate|schedule|policy|exception|assign|signup|withdraw|propose|accept|decline|retract|attendance|complete|cancel|read|cash-open|cash-book|cash-reverse|cash-close'], methods: ['POST'])]
    public function action(string $operation, int $id, Request $request, BarDutyWorkflow $duty, BarCashWorkflow $cash): Response
    {
        $this->access->user();
        if (!$this->isCsrfTokenValid('bar_duty_'.$operation.'_'.$id, $request->request->getString('_token'))) { throw $this->createAccessDeniedException('Ungültiger CSRF-Token.'); }
        if (in_array($operation, ['plan', 'define', 'deactivate', 'schedule', 'policy', 'exception', 'assign', 'attendance', 'complete', 'cancel'], true)) {
            $this->requireManagement();
        }
        if (str_starts_with($operation, 'cash-')) {
            $shift = $operation === 'cash-open' ? $this->em->find(BarShift::class, $id)
                : $this->em->find(BarCashSession::class, $id)?->getShift();
            if ($shift === null) { throw $this->createNotFoundException(); }
            $cash->requireAccess($shift, $this->backend());
        }
        $p = $request->request;
        $target = null;
        $planned = null;
        try {
            switch ($operation) {
                case 'plan':
                    $planned = $duty->plan($p->getString('title'), $p->getString('location'), $p->getString('time'), self::integer($p->getString('minutes')), self::integer($p->getString('capacity')),
                        $p->getString('date'), $p->getString('recurrence'), $p->getString('until'), $p->all('weekdays'));
                    break;
                case 'define': $duty->define($p->getString('title'), $p->getString('location'), $p->getString('time'), self::integer($p->getString('minutes')), self::integer($p->getString('capacity'))); break;
                case 'deactivate': $duty->deactivateDefinition($id); break;
                case 'schedule': $target = $duty->schedule($id, $p->getString('date'))->getId(); break;
                case 'policy': $duty->configure(self::integer($p->getString('year')), self::integer($p->getString('minutes')), $p->getString('cash') === '1', $p->getString('reason')); break;
                case 'exception': $duty->exception(self::integer($p->getString('member')), self::integer($p->getString('year')), $p->getString('minutes') === '' ? null : self::integer($p->getString('minutes')), $p->getString('reason')); break;
                case 'assign': $target = $duty->assign($id, self::integer($p->getString('member')))->getShift()->getId(); break;
                case 'signup': $target = $duty->assign($id)->getShift()->getId(); break;
                case 'withdraw': $duty->cancelAssignment($id, $p->getString('reason')); break;
                case 'propose': $duty->propose($id, self::integer($p->getString('member')), $p->getString('counterpart') === '' ? null : self::integer($p->getString('counterpart'))); break;
                case 'accept': $duty->decide($id, 'accepted'); break;
                case 'decline': $duty->decide($id, 'declined'); break;
                case 'retract': $duty->decide($id, 'withdrawn'); break;
                case 'attendance': $duty->attendance($id, $p->getString('attendance'), self::integer($p->getString('minutes')), $p->getString('reason')); break;
                case 'complete': $duty->finish($id, 'completed', $p->getString('reason')); $target = $id; break;
                case 'cancel': $duty->finish($id, 'cancelled', $p->getString('reason')); $target = $id; break;
                case 'cash-open': $cash->open($id, $p->getString('amount'), $p->getString('reason'), $this->backend()); $target = $id; break;
                case 'cash-book': $cash->book($id, $p->getString('direction'), $p->getString('amount'), $p->getString('reason'), $p->getString('reference'), $this->backend()); break;
                case 'cash-reverse': $cash->reverse($id, self::integer($p->getString('entry')), $p->getString('reason'), $this->backend()); break;
                case 'cash-close': $cash->close($id, $p->getString('amount'), $p->getString('reason'), $this->backend()); break;
                case 'read':
                    $duty->transaction(function () use ($duty, $id): void {
                        $row = $duty->locked(BarDutyNotification::class, $id);
                        if ($row->getMember()->getId() !== $this->access->member()->getId()) { throw $this->createAccessDeniedException(); }
                        $row->markRead();
                    }); break;
            }
            $this->addFlash('success', $planned === null ? 'Thekendienstvorgang gespeichert.' : count($planned).' Veranstaltungstermin(e) mit Thekendienst angelegt.');
        } catch (\DomainException $e) { $this->addFlash('danger', $e->getMessage()); }
        if ($planned !== null) { return $this->redirectToRoute($this->route('bar_duty'), ['month' => $planned[0]->getStartsAt()->format('Y-m')], Response::HTTP_SEE_OTHER); }
        if ($operation === 'plan') { return $this->redirectToRoute($this->route('bar_duty_plan'), [], Response::HTTP_SEE_OTHER); }
        if ($target !== null) { return $this->redirectToRoute($this->route('bar_duty_shift'), ['id' => $target], Response::HTTP_SEE_OTHER); }
        return $this->redirectToRoute($this->route(in_array($operation, ['define', 'deactivate', 'policy', 'exception'], true) ? 'bar_duty_settings' : 'bar_duty'), [], Response::HTTP_SEE_OTHER);
    }

    private function exchanges(): array
    {
        return $this->em->getRepository(BarDutyExchange::class)->createQueryBuilder('e')->join('e.source', 'a')->join('a.shift', 's')
            ->where('s.club = :club AND (a.member = :member OR e.target = :member)')->setParameter('club', $this->access->club())
            ->setParameter('member', $this->access->member())->orderBy('e.id', 'DESC')->setMaxResults(100)->getQuery()->getResult();
    }
    private function members(bool $active = false): array
    {
        $members = $this->em->getRepository(Member::class)->findBy(['club' => $this->access->club()], ['id' => 'ASC']);
        return $active ? array_values(array_filter($members, static fn (Member $member): bool => $member->isActiveMember())) : $members;
    }
    private function year(Request $request): int
    {
        try { $year = self::integer($request->query->getString('year', date('Y'))); BarDutyWorkflow::year($year); return $year; }
        catch (\DomainException) { throw $this->createNotFoundException('Ungültiges Jahr.'); }
    }
    private static function integer(string $value): int
    {
        if (!preg_match('/^\d{1,7}$/D', $value)) { throw new \DomainException('Ganzzahlige Angabe erforderlich.'); }
        return (int) $value;
    }
    private function csv(array $rows, string $filename): Response
    {
        $response = new StreamedResponse(static function () use ($rows): void {
            $out = fopen('php://output', 'wb');
            fwrite($out, "\xEF\xBB\xBF");
            foreach ($rows as $row) {
                $safe = array_map(static fn ($cell): string => is_string($cell) && preg_match('/^[=+@\-\t\r]/u', $cell) ? "'".$cell : (string) $cell, $row);
                fputcsv($out, $safe, ';', escape: '');
            }
            fclose($out);
        });
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="'.$filename.'"');
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        return $response;
    }
}
