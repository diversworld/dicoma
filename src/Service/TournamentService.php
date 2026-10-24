<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\TeamMember;
use App\Entity\Tournament;
use App\Entity\TournamentEntry;
use App\Entity\TournamentMatch;
use App\Entity\TournamentPayment;
use App\Entity\TournamentPlayer;
use App\Enum\MemberSportStatus;
use App\Enum\TeamMemberRole;
use App\Enum\TournamentStatus;
use Doctrine\DBAL\LockMode;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\ORM\EntityManagerInterface;

final class TournamentService
{
    public function __construct(private readonly EntityManagerInterface $em, private readonly CompetitionAccess $access) {}

    /** @return list<TournamentEntry> */
    public function entries(Tournament $tournament): array
    {
        return $this->em->getRepository(TournamentEntry::class)->findBy(['tournament' => $tournament], ['groupName' => 'ASC', 'id' => 'ASC']);
    }

    /** @return list<TournamentMatch> */
    public function matches(Tournament $tournament): array
    {
        return $this->em->getRepository(TournamentMatch::class)->findBy(['tournament' => $tournament], ['roundNumber' => 'ASC', 'startsAt' => 'ASC', 'id' => 'ASC']);
    }

    public function paidCents(TournamentEntry $entry): int
    {
        return (int) $this->em->getRepository(TournamentPayment::class)->createQueryBuilder('p')
            ->select('COALESCE(SUM(p.amountCents), 0)')->where('p.entry = :entry')->setParameter('entry', $entry)->getQuery()->getSingleScalarResult();
    }

    public function assertRosterOpen(Tournament $tournament): void
    {
        if (!$this->rosterIsOpen($tournament)) {
            throw new \DomainException('Meldungen und Kaderänderungen sind für dieses Turnier geschlossen.');
        }
    }

    public function rosterIsOpen(Tournament $tournament): bool
    {
        return in_array($tournament->getStatus(), [TournamentStatus::DRAFT, TournamentStatus::OPEN], true)
            && ($tournament->getRegistrationDeadline() === null || $tournament->getRegistrationDeadline() >= new \DateTimeImmutable());
    }

    private function transaction(Tournament $tournament, callable $operation): mixed
    {
        return $this->em->getConnection()->transactional(function () use ($tournament, $operation): mixed {
            if ($tournament->getId() !== null && $this->em->getConnection()->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
                $this->em->lock($tournament, LockMode::PESSIMISTIC_WRITE);
                $this->em->refresh($tournament);
            }
            $result = $operation();
            $this->em->flush();
            return $result;
        });
    }

    public function register(TournamentEntry $entry): void
    {
        $tournament = $entry->getTournament();
        $this->access->require($this->access->managesEntry($entry));
        $this->transaction($tournament, function () use ($tournament, $entry): void {
            $this->assertRosterOpen($tournament);
            if (!$this->access->managesClub($tournament->getClub()) && $tournament->getStatus() !== TournamentStatus::OPEN) {
                throw new \DomainException('Dieses Turnier ist noch nicht zur Meldung geöffnet.');
            }
            $active = array_filter($this->entries($tournament), static fn (TournamentEntry $e): bool => !$e->isWithdrawn());
            if (count($active) >= $tournament->getMaxTeams()) { throw new \DomainException('Die maximale Mannschaftszahl ist erreicht.'); }
            $team = $entry->getTeam();
            if ($team !== null && (!$team->isActive()
                || mb_strtolower(trim($team->getSport()?->getShortName() ?: $team->getSport()?->getName() ?? '')) !== $tournament->getSportCode())) {
                throw new \DomainException('Mannschaft und Turnier müssen dieselbe Sportkennung verwenden; die Mannschaft muss aktiv sein.');
            }
            foreach ($this->entries($tournament) as $existing) {
                if (($team !== null && $existing->getTeam()?->getId() === $team->getId())
                    || mb_strtolower($existing->getName()) === mb_strtolower($entry->getName())) {
                    throw new \DomainException('Diese Mannschaft wurde bereits gemeldet.');
                }
            }
            $entry->setFeeCents($tournament->getFeeCents());
            $this->em->persist($entry);
        });
    }

    public function eligibility(TeamMember $member, Tournament $tournament): ?string
    {
        $start = $tournament->getStartsAt();
        $end = $tournament->getEndsAt();
        if ($start === null || $end === null || !$member->getTeam()?->isActive()
            || !$member->getMember()?->isActiveMember()
            || ($member->getMember()->getJoinedAt() !== null && $member->getMember()->getJoinedAt()->format('Y-m-d') > $start->format('Y-m-d'))
            || ($member->getMember()->getLeftAt() !== null && $member->getMember()->getLeftAt()->format('Y-m-d') < $end->format('Y-m-d'))
            || !$member->isCompetitionEligible() || !$member->isAssignedOn($start) || !$member->isAssignedOn($end)
            || !in_array($member->getRole(), [TeamMemberRole::PLAYER, TeamMemberRole::TEAM_LEADER], true)
            || ($member->getEligibilityUntil() !== null && $member->getEligibilityUntil()->format('Y-m-d') < $end->format('Y-m-d'))) {
            return 'Keine gültige Spielberechtigung für den gesamten Turnierzeitraum.';
        }
        foreach ($member->getMember()->getSports() as $sport) {
            if ($sport->getSport()?->getId() === $member->getTeam()->getSport()?->getId()
                && $sport->getStatus() === MemberSportStatus::ACTIVE
                && ($sport->getJoinedAt() === null || $sport->getJoinedAt()->format('Y-m-d') <= $start->format('Y-m-d'))
                && ($sport->getLeftAt() === null || $sport->getLeftAt()->format('Y-m-d') >= $end->format('Y-m-d'))) {
                return null;
            }
        }
        return 'Keine aktive Sportzuordnung für den Turnierzeitraum.';
    }

    public function addPlayer(TournamentPlayer $player): void
    {
        $entry = $player->getEntry();
        $this->access->require($this->access->managesEntry($entry));
        $this->transaction($entry->getTournament(), function () use ($player, $entry): void {
            $this->assertRosterOpen($entry->getTournament());
            if ($entry->isWithdrawn()) { throw new \DomainException('Die Mannschaftsmeldung wurde zurückgezogen.'); }
            if ($this->em->getRepository(TournamentPlayer::class)->count(['entry' => $entry]) >= $entry->getTournament()->getMaxPlayers()) {
                throw new \DomainException('Der Turnierkader ist vollständig.');
            }
            $assignment = $player->getTeamMember();
            if ($entry->getTeam() === null && $assignment !== null) {
                throw new \DomainException('Externe Spieler dürfen nicht mit einem fremden Vereinskader verknüpft werden.');
            }
            if ($entry->getTeam() !== null) {
                if ($assignment === null || $assignment->getTeam()?->getId() !== $entry->getTeam()->getId()) {
                    throw new \DomainException('Spieler müssen aus dem Kader der gemeldeten Mannschaft stammen.');
                }
                if (($error = $this->eligibility($assignment, $entry->getTournament())) !== null) { throw new \DomainException($error); }
                $player->setName($assignment->getMember()->getFullName());
                $player->setPosition($assignment->getPosition());
                $player->setJerseyNumber($assignment->getJerseyNumber());
            } elseif (!$this->access->managesClub($entry->getTournament()->getClub())) {
                throw new \DomainException('Externe Spieler werden vom Veranstalter geprüft.');
            }
            if (trim($player->getName()) === '' || trim($player->getEligibilityEvidence()) === '') {
                throw new \DomainException('Name und dokumentierter Berechtigungsnachweis sind erforderlich.');
            }
            foreach ($this->em->getRepository(TournamentPlayer::class)->createQueryBuilder('p')
                ->join('p.entry', 'e')->where('e.tournament = :tournament')->setParameter('tournament', $entry->getTournament())->getQuery()->getResult() as $existing) {
                if (($assignment !== null && $existing->getTeamMember()?->getMember()?->getPerson()?->getId() === $assignment->getMember()?->getPerson()?->getId())
                    || ($existing->getEntry()->getId() === $entry->getId() && mb_strtolower($existing->getName()) === mb_strtolower($player->getName()))) {
                    throw new \DomainException('Der Spieler ist bereits registriert.');
                }
                if ($existing->getEntry()->getId() === $entry->getId() && $player->getJerseyNumber() !== null
                    && $existing->getJerseyNumber() === $player->getJerseyNumber()) {
                    throw new \DomainException('Die Rückennummer ist in diesem Turnierkader bereits vergeben.');
                }
            }
            $this->em->persist($player);
        });
    }

    public function removePlayer(TournamentPlayer $player): void
    {
        $this->access->require($this->access->managesEntry($player->getEntry()));
        $this->transaction($player->getEntry()->getTournament(), function () use ($player): void {
            $this->assertRosterOpen($player->getEntry()->getTournament());
            $this->em->remove($player);
        });
    }

    public function withdraw(TournamentEntry $entry): void
    {
        $this->access->require($this->access->managesEntry($entry));
        $this->transaction($entry->getTournament(), function () use ($entry): void {
            $this->assertRosterOpen($entry->getTournament());
            if ($this->matches($entry->getTournament()) !== []) { throw new \DomainException('Vor Rückzug muss der Spielplan ausdrücklich bereinigt werden.'); }
            $entry->setWithdrawn(true);
        });
    }

    public function saveMatch(TournamentMatch $match): void
    {
        $tournament = $match->getTournament();
        $this->access->require($this->access->managesClub($tournament->getClub()));
        $this->transaction($tournament, function () use ($tournament, $match): void {
            if (!in_array($tournament->getStatus(), [TournamentStatus::DRAFT, TournamentStatus::OPEN], true)) {
                throw new \DomainException('Der Spielplan ist für Änderungen geschlossen.');
            }
            $home = $match->getHome(); $away = $match->getAway();
            if ($home === null || $away === null || $home->getId() === $away->getId()
                || $home->getTournament()->getId() !== $tournament->getId() || $away->getTournament()->getId() !== $tournament->getId()
                || $home->isWithdrawn() || $away->isWithdrawn() || $home->getGroupName() !== $away->getGroupName()) {
                throw new \DomainException('Es werden zwei unterschiedliche aktive Mannschaften derselben Turniergruppe benötigt.');
            }
            if ($match->getDurationMinutes() < 5 || $match->getDurationMinutes() > 360) {
                throw new \DomainException('Ein Zeitfenster muss zwischen 5 und 360 Minuten liegen.');
            }
            $end = $match->getStartsAt()?->modify('+' . $match->getDurationMinutes() . ' minutes');
            if ($match->getStartsAt() !== null && ($match->getStartsAt() < $tournament->getStartsAt() || $end > $tournament->getEndsAt())) {
                throw new \DomainException('Das gesamte Spielfenster muss im Turnierzeitraum liegen.');
            }
            foreach ($this->matches($tournament) as $existing) {
                if ($existing->getId() === $match->getId() || $existing->isCancelled() || $match->isCancelled()) { continue; }
                $sameTeam = array_intersect([$home->getId(), $away->getId()], [$existing->getHome()->getId(), $existing->getAway()->getId()]) !== [];
                $overlap = $end !== null && $existing->getStartsAt() !== null
                    && $match->getStartsAt() < $existing->getStartsAt()->modify('+' . $existing->getDurationMinutes() . ' minutes')
                    && $end > $existing->getStartsAt();
                $sameLocation = mb_strtolower(trim($match->getLocation() ?: $tournament->getLocation()))
                    === mb_strtolower(trim($existing->getLocation() ?: $tournament->getLocation()));
                if (($sameTeam && ($existing->getRoundNumber() === $match->getRoundNumber() || $overlap)) || ($overlap && $sameLocation)) {
                    throw new \DomainException('Mannschaften dürfen nicht doppelt pro Runde oder zeitgleich spielen. Ein Spielfeld darf nicht doppelt belegt sein.');
                }
            }
            $this->em->persist($match);
        });
    }

    public function resetSchedule(Tournament $tournament): void
    {
        $this->access->require($this->access->managesClub($tournament->getClub()));
        $this->transaction($tournament, function () use ($tournament): void {
            if (!in_array($tournament->getStatus(), [TournamentStatus::DRAFT, TournamentStatus::OPEN], true)) {
                throw new \DomainException('Nur ein noch nicht gestarteter Spielplan darf zurückgesetzt werden.');
            }
            $matches = $this->matches($tournament);
            foreach ($matches as $match) {
                if ($match->getHomeScore() !== null || $match->getAwayScore() !== null || $match->getResultHistory() !== []) {
                    throw new \DomainException('Spiele mit Ergebnisnachweisen dürfen nicht gelöscht werden.');
                }
            }
            foreach ($matches as $match) { $this->em->remove($match); }
        });
    }

    public function generate(Tournament $tournament, int $slotMinutes): void
    {
        $this->access->require($this->access->managesClub($tournament->getClub()));
        $this->transaction($tournament, function () use ($tournament, $slotMinutes): void {
            if (!in_array($tournament->getStatus(), [TournamentStatus::DRAFT, TournamentStatus::OPEN], true)
                || $this->matches($tournament) !== []) { throw new \DomainException('Ein Spielplan wird nur vor Turnierbeginn und ohne vorhandene Spiele erzeugt.'); }
            if ($slotMinutes < 5 || $slotMinutes > 360) { throw new \DomainException('Ein Zeitfenster muss zwischen 5 und 360 Minuten liegen.'); }
            $groups = [];
            foreach ($this->entries($tournament) as $entry) {
                if (!$entry->isWithdrawn()) { $groups[$entry->getGroupName()][] = $entry; }
            }
            if ($groups === []) { throw new \DomainException('Bitte zuerst mindestens zwei Mannschaften melden.'); }
            $planned = [];
            $time = $tournament->getStartsAt();
            if ($time === null || $tournament->getEndsAt() === null) { throw new \DomainException('Turnierzeitraum fehlt.'); }
            foreach ($groups as $entries) {
                if (count($entries) < 2) { throw new \DomainException('Jede Gruppe benötigt mindestens zwei Mannschaften.'); }
                if (count($entries) % 2 !== 0) { $entries[] = null; }
                $n = count($entries);
                for ($round = 1; $round < $n; ++$round) {
                    for ($i = 0; $i < $n / 2; ++$i) {
                        $home = $entries[$i]; $away = $entries[$n - 1 - $i];
                        if ($home === null || $away === null) { continue; }
                        $end = $time->modify('+' . $slotMinutes . ' minutes');
                        if ($end > $tournament->getEndsAt()) { throw new \DomainException('Die Spiele passen nicht in den Turnierzeitraum. Zeitraum erweitern oder Zeitfenster verkürzen.'); }
                        $match = new TournamentMatch($tournament);
                        $match->setHome($round % 2 === 0 ? $away : $home); $match->setAway($round % 2 === 0 ? $home : $away);
                        $match->setRoundNumber($round); $match->setStartsAt($time); $match->setLocation($tournament->getLocation());
                        $match->setDurationMinutes($slotMinutes);
                        $planned[] = $match;
                        $time = $end;
                    }
                    $last = array_pop($entries);
                    array_splice($entries, 1, 0, [$last]);
                }
            }
            foreach ($planned as $match) { $this->em->persist($match); }
        });
    }

    public function recordResult(TournamentMatch $match, int $home, int $away, string $actor, string $reason, int $version): void
    {
        $tournament = $match->getTournament();
        $this->access->require($this->access->managesClub($tournament->getClub()));
        $this->transaction($tournament, function () use ($tournament, $match, $home, $away, $actor, $reason, $version): void {
            if ($tournament->getStatus() !== TournamentStatus::RUNNING || $match->isCancelled()) { throw new \DomainException('Ergebnisse können nur für laufende Turniere und aktive Spiele erfasst werden.'); }
            $this->em->lock($match, LockMode::OPTIMISTIC, $version);
            $match->recordResult($home, $away, $actor, $reason);
        });
    }

    /** @return array<string, list<array<string, int|string>>> */
    public function standings(Tournament $tournament): array
    {
        $rows = [];
        foreach ($this->entries($tournament) as $entry) {
            if ($entry->isWithdrawn()) { continue; }
            $rows[$entry->getId()] = ['id' => $entry->getId(), 'name' => $entry->getName(), 'group' => $entry->getGroupName(),
                'played' => 0, 'won' => 0, 'drawn' => 0, 'lost' => 0, 'for' => 0, 'against' => 0, 'difference' => 0, 'points' => 0];
        }
        foreach ($this->matches($tournament) as $match) {
            if ($match->isCancelled() || $match->getHomeScore() === null || $match->getAwayScore() === null) { continue; }
            $home = $match->getHome()->getId(); $away = $match->getAway()->getId();
            if (!isset($rows[$home], $rows[$away])) { continue; }
            foreach ([[$home, $match->getHomeScore(), $match->getAwayScore()], [$away, $match->getAwayScore(), $match->getHomeScore()]] as [$id, $score, $opponent]) {
                ++$rows[$id]['played']; $rows[$id]['for'] += $score; $rows[$id]['against'] += $opponent;
                $outcome = $score > $opponent ? 'won' : ($score === $opponent ? 'drawn' : 'lost');
                ++$rows[$id][$outcome];
                $rows[$id]['points'] += match ($outcome) { 'won' => $tournament->getWinPoints(), 'drawn' => $tournament->getDrawPoints(), default => $tournament->getLossPoints() };
                $rows[$id]['difference'] = $rows[$id]['for'] - $rows[$id]['against'];
            }
        }
        $groups = [];
        foreach ($rows as $row) { $groups[$row['group']][] = $row; }
        foreach ($groups as &$group) {
            usort($group, static fn (array $a, array $b): int => ($b['points'] <=> $a['points']) ?: ($b['difference'] <=> $a['difference']) ?: ($b['for'] <=> $a['for']) ?: strnatcasecmp($a['name'], $b['name']));
            $previous = null; $rank = 0;
            foreach ($group as $i => &$row) {
                $key = [$row['points'], $row['difference'], $row['for']];
                if ($key !== $previous) { $rank = $i + 1; }
                $row['rank'] = $rank; $previous = $key;
            }
            unset($row);
        }
        unset($group);
        return $groups;
    }

    public function transition(Tournament $tournament, TournamentStatus $next): void
    {
        $this->access->require($this->access->managesClub($tournament->getClub()));
        $this->transaction($tournament, function () use ($tournament, $next): void {
            $current = $tournament->getStatus();
            $allowed = match ($current) {
                TournamentStatus::DRAFT => [TournamentStatus::OPEN, TournamentStatus::CANCELLED],
                TournamentStatus::OPEN => [TournamentStatus::RUNNING, TournamentStatus::CANCELLED],
                TournamentStatus::RUNNING => [TournamentStatus::COMPLETED, TournamentStatus::CANCELLED],
                default => [],
            };
            if (!in_array($next, $allowed, true)) { throw new \DomainException('Dieser Statuswechsel ist nicht erlaubt.'); }
            if ($next === TournamentStatus::RUNNING) {
                if (array_filter($this->matches($tournament), static fn (TournamentMatch $match): bool => !$match->isCancelled()) === []) {
                    throw new \DomainException('Vor Turnierstart wird mindestens ein aktives Spiel benötigt.');
                }
                foreach ($this->entries($tournament) as $entry) {
                    if ($entry->isWithdrawn()) { continue; }
                    $players = $this->em->getRepository(TournamentPlayer::class)->findBy(['entry' => $entry]);
                    if (count($players) < $tournament->getMinPlayers()) { throw new \DomainException('Ein Turnierkader unterschreitet die Mindestspielerzahl.'); }
                    foreach ($players as $player) {
                        if ($player->getTeamMember() !== null && ($error = $this->eligibility($player->getTeamMember(), $tournament)) !== null) { throw new \DomainException($error); }
                    }
                }
            }
            if ($next === TournamentStatus::COMPLETED) {
                $matches = [];
                $played = 0;
                foreach ($this->matches($tournament) as $match) {
                    if (!$match->isCancelled() && ($match->getHomeScore() === null || $match->getAwayScore() === null)) {
                        throw new \DomainException('Vor Abschluss müssen alle aktiven Spiele ein Ergebnis haben.');
                    }
                    if (!$match->isCancelled()) { ++$played; }
                    $matches[] = ['home' => $match->getHome()->getName(), 'away' => $match->getAway()->getName(),
                        'round' => $match->getRoundNumber(), 'startsAt' => $match->getStartsAt()?->format(DATE_ATOM), 'location' => $match->getLocation(), 'durationMinutes' => $match->getDurationMinutes(),
                        'homeScore' => $match->getHomeScore(), 'awayScore' => $match->getAwayScore(), 'cancelled' => $match->isCancelled(), 'history' => $match->getResultHistory()];
                }
                if ($played === 0) { throw new \DomainException('Es muss mindestens ein gewertetes Spiel vorliegen.'); }
                $rosters = [];
                foreach ($this->entries($tournament) as $entry) {
                    $rosters[] = ['team' => $entry->getName(), 'group' => $entry->getGroupName(), 'withdrawn' => $entry->isWithdrawn(), 'feeCents' => $entry->getFeeCents(), 'players' => array_map(static fn (TournamentPlayer $p): array =>
                        ['name' => $p->getName(), 'position' => $p->getPosition(), 'number' => $p->getJerseyNumber(), 'eligibility' => $p->getEligibilityEvidence()],
                        $this->em->getRepository(TournamentPlayer::class)->findBy(['entry' => $entry]))];
                }
                $tournament->archive(['name' => $tournament->getName(), 'sport' => $tournament->getSportCode(),
                    'at' => (new \DateTimeImmutable())->format(DATE_ATOM), 'standings' => $this->standings($tournament), 'matches' => $matches, 'rosters' => $rosters]);
            } else { $tournament->setStatus($next); }
        });
    }

    public function payment(TournamentEntry $entry, int $amount, string $reference, string $actor): void
    {
        $this->access->require($this->access->managesClub($entry->getTournament()->getClub()));
        $this->transaction($entry->getTournament(), function () use ($entry, $amount, $reference, $actor): void {
            $paid = $this->paidCents($entry);
            if ($amount === 0 || abs($amount) > 99999999 || $paid + $amount < 0 || $paid + $amount > $entry->getFeeCents()
                || trim($reference) === '' || mb_strlen($reference) > 120) {
                throw new \DomainException('Ungültiger Betrag oder Beleg: keine Überzahlung und keine Erstattung über die Zahlungssumme hinaus.');
            }
            if ($this->em->getRepository(TournamentPayment::class)->count(['entry' => $entry, 'reference' => trim($reference)]) > 0) {
                throw new \DomainException('Dieser Zahlungsbeleg wurde bereits erfasst.');
            }
            $this->em->persist(new TournamentPayment($entry, $amount, trim($reference), $actor));
        });
    }
}
