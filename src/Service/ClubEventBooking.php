<?php

declare(strict_types=1);
namespace App\Service;

use App\Entity\{Club, ClubEvent, ClubEventPayment, ClubEventRegistration, Member, Person, User};
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class ClubEventBooking
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    public function isMember(ClubEvent $event, Person $person): bool
    {
        $query = $this->em->createQueryBuilder()->select('m')->from(Member::class, 'm')
            ->where('m.club = :club AND m.person = :person')
            ->setParameter('club', $event->getClub())->setParameter('person', $person)->setMaxResults(1)->getQuery();
        $member = $this->em->getConnection()->isTransactionActive() ? ($this->current($query)[0] ?? null) : $query->getOneOrNullResult();
        return $member !== null && $member->isActiveMember();
    }

    public function canSee(ClubEvent $event, ?User $user): bool
    {
        if (!$event->isPublished() || $event->isCancelled()) { return false; }
        return $event->getPricing() === 'paid'
            || ($user !== null && $user->getPerson() !== null && $this->isMember($event, $user->getPerson()));
    }

    /** @return list<ClubEvent> */
    public function visibleUpcoming(Club $club, ?User $user, ?int $limit = null): array
    {
        $query = $this->em->getRepository(ClubEvent::class)->createQueryBuilder('e')
            ->where('e.club = :club AND e.published = true AND e.cancelled = false AND e.endsAt >= :now')
            ->setParameter('club', $club)->setParameter('now', new \DateTimeImmutable())
            ->orderBy('e.startsAt', 'ASC')->addOrderBy('e.id', 'ASC')->getQuery();
        $visible = array_values(array_filter($query->getResult(), fn (ClubEvent $event): bool => $this->canSee($event, $user)));
        return $limit === null ? $visible : array_slice($visible, 0, $limit);
    }

    public function register(int $id, User $user): ClubEventRegistration
    {
        $person = $user->getPerson();
        if (!$user->isVerified() || $person === null || $person->getId() === null) {
            throw new AccessDeniedException('Ein bestätigtes Personenkonto ist erforderlich.');
        }
        return $this->transaction(function () use ($id, $person): ClubEventRegistration {
            $event = $this->lockedEvent($id);
            if (!$event->isPublished() || $event->isCancelled() || $event->getStartsAt() <= new \DateTimeImmutable()
                || ($event->getDeadline() !== null && $event->getDeadline() < new \DateTimeImmutable())) {
                throw new \DomainException('Die Anmeldung für diese Veranstaltung ist geschlossen.');
            }
            $activeMember = $this->isMember($event, $person);
            if ($event->getPricing() === 'members_free' && !$activeMember) {
                throw new AccessDeniedException('Diese kostenfreie Veranstaltung ist aktiven Vereinsmitgliedern vorbehalten.');
            }
            $existing = $this->em->getRepository(ClubEventRegistration::class)->createQueryBuilder('r')
                ->where('r.event = :event AND r.person = :person')->setParameter('event', $event)->setParameter('person', $person)
                ->setMaxResults(1)->getQuery();
            if ($this->current($existing) !== []) {
                throw new \DomainException('Für diese Veranstaltung besteht bereits eine Anmeldung oder Stornierung.');
            }
            $status = $event->getCapacity() !== null && $this->occupied($event) >= $event->getCapacity() ? 'waitlisted' : 'registered';
            $registration = new ClubEventRegistration($event, $person, $status,
                $activeMember ? $event->getMemberFeeCents() : $event->getGuestFeeCents(), $activeMember ? 'member' : 'guest');
            $this->em->persist($registration); $this->em->flush();
            return $registration;
        });
    }

    public function cancel(ClubEventRegistration $registration, Person $person): void
    {
        $this->transaction(function () use ($registration, $person): void {
            $event = $this->lockedEvent($registration->getEvent()->getId());
            $this->em->refresh($registration, LockMode::PESSIMISTIC_WRITE);
            if ($registration->getPerson()->getId() !== $person->getId()) { throw new AccessDeniedException('Fremde Anmeldung.'); }
            if ($registration->getStatus() === 'cancelled' || $event->isCancelled() || $event->getStartsAt() <= new \DateTimeImmutable()) {
                throw new \DomainException('Diese Anmeldung kann nicht mehr storniert werden.');
            }
            $wasRegistered = $registration->getStatus() === 'registered';
            $registration->cancel(); $this->em->flush();
            if ($wasRegistered) {
                $query = $this->em->getRepository(ClubEventRegistration::class)->createQueryBuilder('r')
                    ->where('r.event = :event AND r.status = :status')->setParameter('event', $event)->setParameter('status', 'waitlisted')
                    ->orderBy('r.registeredAt', 'ASC')->addOrderBy('r.id', 'ASC')->getQuery();
                $waiting = $this->current($query);
                foreach ($waiting as $next) {
                    if ($event->getPricing() === 'members_free' && !$this->isMember($event, $next->getPerson())) {
                        $next->disqualify();
                        continue;
                    }
                    $next->promote();
                    break;
                }
                $this->em->flush();
            }
        });
    }

    public function recordPayment(ClubEventRegistration $registration, int $amount, string $method, string $reference, string $actor): void
    {
        $this->transaction(function () use ($registration, $amount, $method, $reference, $actor): void {
            $event = $this->lockedEvent($registration->getEvent()->getId());
            $this->em->refresh($registration, LockMode::PESSIMISTIC_WRITE);
            $query = $this->em->getRepository(ClubEventPayment::class)->createQueryBuilder('p')
                ->where('p.registration = :registration AND p.reference = :reference')
                ->setParameter('registration', $registration)->setParameter('reference', trim($reference))->setMaxResults(1)->getQuery();
            if ($this->current($query) !== []) {
                throw new \DomainException('Diese Belegreferenz wurde bereits gebucht.');
            }
            $paid = $this->paid($registration);
            if (in_array($registration->getStatus(), ['waitlisted', 'ineligible'], true) || (($registration->getStatus() === 'cancelled' || $event->isCancelled()) && $amount > 0)
                || $paid + $amount < 0 || $paid + $amount > $registration->getFeeCents()) {
                throw new \DomainException('Zahlung nicht möglich: Status, offener Betrag oder Erstattung prüfen.');
            }
            $this->em->persist(new ClubEventPayment($registration, $amount, $method, $reference, $actor));
            $this->em->flush();
        });
    }

    public function paid(ClubEventRegistration $registration): int
    {
        $query = $this->em->getRepository(ClubEventPayment::class)->createQueryBuilder('p')
            ->where('p.registration = :registration')->setParameter('registration', $registration)->getQuery();
        $payments = $this->em->getConnection()->isTransactionActive() ? $this->current($query) : $query->getResult();
        return array_sum(array_map(static fn (ClubEventPayment $payment): int => $payment->getAmountCents(), $payments));
    }

    private function occupied(ClubEvent $event): int
    {
        $query = $this->em->getRepository(ClubEventRegistration::class)->createQueryBuilder('r')
            ->where('r.event = :event AND r.status = :status')->setParameter('event', $event)
            ->setParameter('status', 'registered')->getQuery();
        return count($this->current($query));
    }

    private function current(Query $query): array
    {
        return $query->setLockMode(LockMode::PESSIMISTIC_READ)->setHint(Query::HINT_REFRESH, true)->getResult();
    }

    private function transaction(callable $work): mixed
    {
        try {
            return $this->em->getConnection()->transactional($work);
        } catch (\Doctrine\DBAL\Exception\DriverException $e) {
            if ($e->getCode() !== 1020 && !$e instanceof \Doctrine\DBAL\Exception\RetryableException) { throw $e; }
            throw new \DomainException('Gleichzeitige Änderung erkannt. Bitte neu laden und erneut versuchen.', 0, $e);
        }
    }

    private function lockedEvent(int $id): ClubEvent
    {
        $event = $this->em->find(ClubEvent::class, $id, LockMode::PESSIMISTIC_WRITE);
        if (!$event instanceof ClubEvent) { throw new \DomainException('Veranstaltung nicht gefunden.'); }
        $this->em->refresh($event, LockMode::PESSIMISTIC_WRITE);
        return $event;
    }
}
