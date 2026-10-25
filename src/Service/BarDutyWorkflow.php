<?php

declare(strict_types=1);
namespace App\Service;

use App\Entity\{BarDutyAssignment, BarDutyExchange, BarDutyNotification, BarDutyPolicy, BarDutyRequirement, BarShift, BarShiftDefinition, Club, Member};
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class BarDutyWorkflow
{
    public function __construct(private readonly EntityManagerInterface $em, private readonly BarDutyAccess $access) {}

    public function transaction(callable $work): mixed
    {
        $club = $this->access->club();
        try {
            return $this->em->wrapInTransaction(function () use ($club, $work): mixed {
                $this->em->refresh($club, LockMode::PESSIMISTIC_WRITE);
                return $work($club);
            });
        } catch (\Doctrine\DBAL\Exception\DriverException $e) {
            if ($e->getCode() !== 1020 && !$e instanceof \Doctrine\DBAL\Exception\RetryableException) { throw $e; }
            throw new \DomainException('Gleichzeitige Änderung erkannt. Bitte neu laden und erneut versuchen.', 0, $e);
        }
    }

    public function locked(string $class, int $id): object
    {
        if (!$this->em->getConnection()->isTransactionActive()) { throw new \LogicException('Transaction required.'); }
        $entity = $this->em->find($class, $id);
        if (!$entity) { throw new \DomainException('Datensatz nicht gefunden.'); }
        $this->em->refresh($entity, LockMode::PESSIMISTIC_WRITE);
        return $entity;
    }

    public function define(string $title, string $location, string $time, int $minutes, int $capacity): BarShiftDefinition
    {
        $this->access->requireManager();
        return $this->transaction(function (Club $club) use ($title, $location, $time, $minutes, $capacity): BarShiftDefinition {
            $definition = new BarShiftDefinition($club, $title, $location, $time, $minutes, $capacity);
            $this->em->persist($definition);
            return $definition;
        });
    }

    public function deactivateDefinition(int $id): void
    {
        $this->access->requireManager();
        $this->transaction(function () use ($id): void {
            $definition = $this->locked(BarShiftDefinition::class, $id);
            $this->access->ownsClub($definition->getClub());
            $definition->deactivate();
        });
    }

    public function schedule(int $definitionId, string $date): BarShift
    {
        $this->access->requireManager();
        $day = self::date($date);
        return $this->transaction(function () use ($definitionId, $day): BarShift {
            $definition = $this->locked(BarShiftDefinition::class, $definitionId);
            $this->access->ownsClub($definition->getClub());
            if (!$definition->isActive()) { throw new \DomainException('Schichtdefinition ist deaktiviert.'); }
            return $this->scheduleDefinition($definition, $day);
        });
    }

    public function plan(string $title, string $location, string $time, int $minutes, int $capacity, string $date, string $recurrence, string $until, array $weekdays): array
    {
        $this->access->requireManager();
        $start = self::date($date);
        if (!in_array($recurrence, ['once', 'weekly', 'fortnightly'], true)) { throw new \DomainException('Wiederholung muss einmalig, wöchentlich oder zweiwöchentlich sein.'); }
        $dates = [$start];
        if ($recurrence !== 'once') {
            $end = self::date($until);
            if ($end < $start) { throw new \DomainException('Serienende darf nicht vor dem Beginn liegen.'); }
            if ($weekdays === []) { throw new \DomainException('Mindestens einen Wochentag für die Serie auswählen.'); }
            foreach ($weekdays as $weekday) {
                if (!is_string($weekday) || !preg_match('/^[1-7]$/D', $weekday)) { throw new \DomainException('Ungültiger Wochentag.'); }
            }
            $interval = $recurrence === 'weekly' ? 1 : 2;
            $anchor = $start->modify('monday this week'); $dates = [];
            for ($day = $start; $day <= $end; $day = $day->modify('+1 day')) {
                $week = intdiv((int) $anchor->diff($day)->format('%a'), 7);
                if ($week % $interval === 0 && in_array($day->format('N'), $weekdays, true)) { $dates[] = $day; }
            }
            if ($dates === []) { throw new \DomainException('Im gewählten Zeitraum liegt kein passender Serientermin.'); }
        }
        return $this->transaction(function (Club $club) use ($title, $location, $time, $minutes, $capacity, $dates): array {
            $definition = new BarShiftDefinition($club, $title, $location, $time, $minutes, $capacity);
            $definition = $this->one(BarShiftDefinition::class, [
                'club' => $club, 'title' => $definition->getTitle(), 'location' => $definition->getLocation(),
                'startTime' => $time, 'durationMinutes' => $minutes, 'capacity' => $capacity, 'active' => true,
            ]) ?? $definition;
            $this->em->persist($definition); $this->em->flush();
            $shifts = [];
            foreach ($dates as $day) { $shifts[] = $this->scheduleDefinition($definition, $day); }
            return $shifts;
        });
    }

    private function scheduleDefinition(BarShiftDefinition $definition, \DateTimeImmutable $day): BarShift
    {
        $start = new \DateTimeImmutable($day->format('Y-m-d').' '.$definition->getStartTime());
        if ($start <= new \DateTimeImmutable()) { throw new \DomainException('Neue Veranstaltungen müssen in der Zukunft liegen.'); }
        $existing = $this->em->getRepository(BarShift::class)->createQueryBuilder('s')->where('s.definition = :definition AND s.startsAt = :start')
            ->setParameter('definition', $definition)->setParameter('start', $start)->getQuery()->setLockMode(LockMode::PESSIMISTIC_READ)->getOneOrNullResult();
        if ($existing !== null) { throw new \DomainException('Diese Veranstaltung existiert bereits. Es wurde kein Termin der Anfrage angelegt.'); }
        $shift = new BarShift($definition, $start);
        $shift->record('created', 'Veranstaltung mit Thekendienst geplant', $this->access->actor());
        $this->em->persist($shift);
        return $shift;
    }

    public function configure(int $year, int $minutes, bool $cash, string $reason): void
    {
        $this->access->requireManager(); self::year($year);
        $this->transaction(function (Club $club) use ($year, $minutes, $cash, $reason): void {
            $policy = $this->one(BarDutyPolicy::class, ['club' => $club, 'year' => $year]) ?? new BarDutyPolicy($club, $year);
            $policy->configure($minutes, $cash, $this->access->actor(), $reason);
            $this->em->persist($policy);
        });
    }

    public function exception(int $memberId, int $year, ?int $minutes, string $reason): void
    {
        $this->access->requireManager(); self::year($year);
        $this->transaction(function () use ($memberId, $year, $minutes, $reason): void {
            $member = $this->member($memberId, false);
            $row = $this->one(BarDutyRequirement::class, ['member' => $member, 'year' => $year]) ?? new BarDutyRequirement($member, $year);
            $row->change($minutes, $reason, $this->access->actor()); $this->em->persist($row);
        });
    }

    public function assign(int $shiftId, ?int $memberId = null): BarDutyAssignment
    {
        if ($memberId !== null) { $this->access->requireManager(); }
        return $this->transaction(function () use ($shiftId, $memberId): BarDutyAssignment {
            $shift = $this->shift($shiftId); $this->future($shift);
            $member = $this->member($memberId ?? $this->access->member()->getId());
            return $this->addAssignment($shift, $member);
        });
    }

    public function cancelAssignment(int $id, string $reason): void
    {
        self::reason($reason);
        $this->transaction(function () use ($id, $reason): void {
            $assignment = $this->assignment($id);
            if (!$this->access->isManager() && $assignment->getMember()->getId() !== $this->access->member()->getId()) { throw new AccessDeniedException(); }
            $this->future($assignment->getShift());
            $assignment->cancel($this->access->actor(), $reason);
            $this->notify($assignment->getMember(), $assignment->getShift(), 'cancel-assignment-'.$id, 'Thekendienst zurückgezogen', $reason);
            $this->withdrawRequests([$assignment]);
        });
    }

    public function propose(int $assignmentId, int $targetId, ?int $counterpartId): BarDutyExchange
    {
        return $this->transaction(function () use ($assignmentId, $targetId, $counterpartId): BarDutyExchange {
            $source = $this->assignment($assignmentId);
            if ($source->getMember()->getId() !== $this->access->member()->getId()) { throw new AccessDeniedException('Nur das eingeteilte Mitglied kann einen Tausch anbieten.'); }
            $this->future($source->getShift()); $this->pendingAssignment($source);
            $target = $this->member($targetId);
            if ($target === $source->getMember()) { throw new \DomainException('Vertretung muss ein anderes Mitglied sein.'); }
            $counterpart = $counterpartId === null ? null : $this->assignment($counterpartId);
            if ($counterpart !== null) {
                $this->future($counterpart->getShift()); $this->pendingAssignment($counterpart);
                if ($counterpart->getMember() !== $target || $counterpart->getShift() === $source->getShift()) { throw new \DomainException('Gegendienst gehört nicht zur Zielperson oder ist dieselbe Schicht.'); }
            }
            foreach ($this->pendingExchanges() as $existing) {
                if ($existing->getSource() === $source || ($counterpart !== null && ($existing->getSource() === $counterpart || $existing->getCounterpart() === $counterpart)) || $existing->getCounterpart() === $source) {
                    throw new \DomainException('Für einen der Dienste besteht bereits eine offene Anfrage.'); }
            }
            $exchange = new BarDutyExchange($source, $target, $counterpart);
            $this->em->persist($exchange); $this->em->flush();
            $this->notify($target, $source->getShift(), 'exchange-'.$exchange->getId(), $counterpart ? 'Thekendiensttausch angefragt' : 'Vertretung angefragt', 'Bitte die Anfrage im Thekendienstbereich annehmen oder ablehnen. Bis zur Annahme bleibt die bisherige Einteilung gültig.');
            return $exchange;
        });
    }

    public function decide(int $id, string $decision): void
    {
        $this->transaction(function () use ($id, $decision): void {
            $exchange = $this->locked(BarDutyExchange::class, $id);
            if ($exchange->getStatus() !== 'pending') { throw new \DomainException('Anfrage wurde bereits entschieden.'); }
            $source = $this->assignment($exchange->getSource()->getId());
            $this->access->ownsClub($source->getShift()->getClub());
            if (!in_array($decision, ['accepted', 'declined', 'withdrawn'], true)) { throw new \DomainException('Unbekannte Tauschaktion.'); }
            $required = $decision === 'withdrawn' ? $source->getMember() : $exchange->getTarget();
            if ($required->getId() !== $this->access->member()->getId()) { throw new AccessDeniedException('Zustimmung muss vom beteiligten Mitglied stammen.'); }
            if ($decision === 'accepted') {
                $this->future($source->getShift()); $this->pendingAssignment($source);
                $target = $this->member($exchange->getTarget()->getId());
                $originalMember = $this->member($source->getMember()->getId());
                $counterpart = $exchange->getCounterpart() ? $this->assignment($exchange->getCounterpart()->getId()) : null;
                $excluded = [$source->getId()];
                if ($counterpart !== null) {
                    $this->future($counterpart->getShift()); $this->pendingAssignment($counterpart);
                    if ($counterpart->getMember() !== $target) { throw new \DomainException('Gegendienst wurde verändert.'); }
                    $excluded[] = $counterpart->getId();
                }
                $this->assertPlace($source->getShift(), $target, $excluded);
                if ($counterpart !== null) { $this->assertPlace($counterpart->getShift(), $originalMember, $excluded); }
                $source->cancel($this->access->actor(), 'Einvernehmlicher Tausch / Vertretung #'.$id);
                if ($counterpart !== null) { $counterpart->cancel($this->access->actor(), 'Einvernehmlicher Tausch #'.$id); }
                $this->em->flush();
                $this->addAssignment($source->getShift(), $target);
                if ($counterpart !== null) { $this->addAssignment($counterpart->getShift(), $originalMember); }
            }
            $exchange->decide($decision, $this->access->actor());
            $this->notify($source->getMember(), $source->getShift(), 'decision-'.$id, 'Tausch / Vertretung: '.$decision, 'Die Anfrage wurde durch das beteiligte Mitglied entschieden.');
        });
    }

    public function attendance(int $assignmentId, string $attendance, int $minutes, string $reason): void
    {
        $this->access->requireManager(); self::reason($reason);
        $this->transaction(function () use ($assignmentId, $attendance, $minutes, $reason): void {
            $assignment = $this->assignment($assignmentId); $shift = $this->shift($assignment->getShift()->getId());
            if ($shift->getStatus() !== 'open' || $shift->getEndsAt() > new \DateTimeImmutable()) { throw new \DomainException('Anwesenheit erst nach Schichtende und vor Abschluss bestätigen.'); }
            $assignment->confirm($attendance, $minutes, $reason, $this->access->actor());
            $shift->record('attendance', 'Dienst #'.$assignmentId.': '.$attendance.', '.$minutes.' Minuten. '.$reason, $this->access->actor());
            $this->em->flush();
            $count = count($assignment->getHistory());
            $this->notify($assignment->getMember(), $shift, 'attendance-'.$assignmentId.'-'.$count, 'Dienststunden bestätigt', $minutes.' Minuten wurden bestätigt. '.$reason);
        });
    }

    public function finish(int $shiftId, string $status, string $reason): void
    {
        $this->access->requireManager(); self::reason($reason);
        $this->transaction(function () use ($shiftId, $status, $reason): void {
            $shift = $this->shift($shiftId);
            $assignments = $this->assignments($shift);
            if ($status === 'completed') {
                if ($shift->getEndsAt() > new \DateTimeImmutable()) { throw new \DomainException('Schicht ist noch nicht beendet.'); }
                foreach ($assignments as $assignment) {
                    if ($assignment->getAttendance() === 'pending') { throw new \DomainException('Zuerst jede Anwesenheit bestätigen.'); }
                }
            } elseif ($status === 'cancelled') {
                foreach ($assignments as $assignment) {
                    if ($assignment->getAttendance() !== 'pending') { throw new \DomainException('Bestätigte Stunden verhindern eine Absage.'); }
                }
                $cash = $this->one(\App\Entity\BarCashSession::class, ['shift' => $shift]);
                if ($cash !== null) { throw new \DomainException('Schicht mit eröffneter Thekenkasse darf nicht abgesagt werden.'); }
                foreach ($assignments as $assignment) { $assignment->cancel($this->access->actor(), $reason); }
            }
            $shift->finish($status, $reason, $this->access->actor());
            $this->withdrawRequests($assignments);
            foreach ($assignments as $assignment) { $this->notify($assignment->getMember(), $shift, 'shift-'.$status.'-'.$assignment->getId(), 'Thekendienst: '.$status, $reason); }
        });
    }

    public function shift(int $id): BarShift
    {
        $shift = $this->locked(BarShift::class, $id); $this->access->ownsClub($shift->getClub()); return $shift;
    }
    public function member(int $id, bool $active = true): Member
    {
        $member = $this->locked(Member::class, $id);
        if ($member->getClub()?->getId() !== $this->access->club()->getId()) { throw new AccessDeniedException('Mitglied gehört nicht zum Verein.'); }
        if ($active && !$member->isActiveMember()) { throw new \DomainException('Mitgliedschaft ist nicht aktiv.'); }
        return $member;
    }
    private function assignment(int $id): BarDutyAssignment
    {
        $row = $this->locked(BarDutyAssignment::class, $id); $this->shift($row->getShift()->getId()); return $row;
    }
    private function future(BarShift $shift): void
    {
        if ($shift->getStatus() !== 'open' || $shift->getStartsAt() <= new \DateTimeImmutable()) { throw new \DomainException('Dienst muss offen sein und in der Zukunft liegen.'); }
    }
    private function pendingAssignment(BarDutyAssignment $row): void
    {
        if ($row->getStatus() !== 'active' || $row->getAttendance() !== 'pending') { throw new \DomainException('Dienst ist nicht mehr tauschbar.'); }
    }
    private function addAssignment(BarShift $shift, Member $member): BarDutyAssignment
    {
        $this->assertPlace($shift, $member);
        $assignment = new BarDutyAssignment($shift, $member, $this->access->actor());
        $this->em->persist($assignment); $this->em->flush();
        $this->notify($member, $shift, 'assigned-'.$assignment->getId(), 'Thekendienst eingeteilt', $shift->getTitle().' am '.$shift->getStartsAt()->format('d.m.Y H:i').' in '.$shift->getLocation().'.');
        return $assignment;
    }
    private function assertPlace(BarShift $shift, Member $member, array $excluded = []): void
    {
        if ($member->getTerminationDate() !== null && $shift->getEndsAt() > $member->getTerminationDate()->modify('+1 day')->setTime(0, 0)) {
            throw new \DomainException('Mitgliedschaft endet vor diesem Dienst.');
        }
        if ($member->getJoinedAt() !== null && $shift->getStartsAt() < $member->getJoinedAt()) {
            throw new \DomainException('Mitgliedschaft beginnt erst nach diesem Dienst.');
        }
        $count = 0;
        foreach ($this->assignments($shift) as $existing) { if (!in_array($existing->getId(), $excluded, true)) { ++$count; } }
        if ($count >= $shift->getCapacity()) { throw new \DomainException('Schicht ist bereits vollständig besetzt.'); }
        $query = $this->em->getRepository(BarDutyAssignment::class)->createQueryBuilder('a')->join('a.shift', 's')->addSelect('s')
            ->where('a.member = :member AND a.status = :active AND s.status != :cancelled AND s.startsAt < :end AND s.endsAt > :start')
            ->setParameter('member', $member)->setParameter('active', 'active')->setParameter('cancelled', 'cancelled')
            ->setParameter('start', $shift->getStartsAt())->setParameter('end', $shift->getEndsAt())->getQuery();
        foreach ($this->current($query) as $existing) {
            if (!in_array($existing->getId(), $excluded, true)) { throw new \DomainException('Mitglied ist in diesem Zeitraum bereits eingeteilt.'); }
        }
    }
    public function assignments(BarShift $shift): array
    {
        return $this->current($this->em->getRepository(BarDutyAssignment::class)->createQueryBuilder('a')
            ->where('a.shift = :shift AND a.status = :active')->setParameter('shift', $shift)->setParameter('active', 'active')->getQuery());
    }
    private function pendingExchanges(): array
    {
        return $this->current($this->em->getRepository(BarDutyExchange::class)->createQueryBuilder('e')
            ->join('e.source', 'a')->join('a.shift', 's')->where('s.club = :club AND e.status = :pending')
            ->setParameter('club', $this->access->club())->setParameter('pending', 'pending')->getQuery());
    }
    private function withdrawRequests(array $assignments): void
    {
        foreach ($this->pendingExchanges() as $exchange) {
            if (in_array($exchange->getSource(), $assignments, true) || in_array($exchange->getCounterpart(), $assignments, true)) {
                $exchange->decide('withdrawn', $this->access->actor());
                $this->notify($exchange->getTarget(), $exchange->getSource()->getShift(), 'withdrawn-'.$exchange->getId(), 'Tauschanfrage zurückgezogen', 'Die Einteilung oder Schicht wurde geändert.');
            }
        }
    }
    public function one(string $class, array $criteria): ?object
    {
        $qb = $this->em->getRepository($class)->createQueryBuilder('r');
        foreach ($criteria as $field => $value) { $qb->andWhere('r.'.$field.' = :'.$field)->setParameter($field, $value); }
        $rows = $this->current($qb->getQuery());
        return $rows[0] ?? null;
    }
    public function current(Query $query): array { return $query->setLockMode(LockMode::PESSIMISTIC_READ)->setHint(Query::HINT_REFRESH, true)->getResult(); }
    public function notify(Member $member, BarShift $shift, string $key, string $subject, string $body): void
    {
        $this->em->persist(new BarDutyNotification($member, $shift, $key, $subject, $body));
    }
    public static function reason(string $reason): void
    {
        if (mb_strlen(trim($reason)) < 10 || mb_strlen($reason) > 500) { throw new \DomainException('Begründung mit 10–500 Zeichen erforderlich.'); }
    }
    public static function year(int $year): void
    {
        if ($year < 2000 || $year > 2100) { throw new \DomainException('Jahr muss zwischen 2000 und 2100 liegen.'); }
    }
    public static function date(string $raw): \DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $raw);
        if (!$date || $date->format('Y-m-d') !== $raw || (int) $date->format('Y') < 2000 || (int) $date->format('Y') > 2100) { throw new \DomainException('Datum ungültig.'); }
        return $date;
    }
}
