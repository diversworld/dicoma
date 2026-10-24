<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\CourseParticipant;
use App\Entity\Courses;
use App\Entity\Member;
use App\Entity\Person;
use App\Entity\CoursePayment;
use App\Enum\CourseParticipantStatus;
use App\Enum\CourseStatus;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

final class FrontendCourseRegistrationService
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    /** @throws \DomainException */
    public function register(int $courseId, Member|Person $attendee): CourseParticipant
    {
        return $this->em->getConnection()->transactional(function () use ($courseId, $attendee): CourseParticipant {
            // Lock the course row to serialize registrations competing for the final place.
            $course = $this->em->find(Courses::class, $courseId, LockMode::PESSIMISTIC_WRITE);
            if (!$course instanceof Courses) {
                throw new \DomainException('Der Kurs wurde nicht gefunden.');
            }
            $this->em->refresh($course);
            if ($course->getStatus() !== CourseStatus::OPEN) {
                throw new \DomainException('Für diesen Kurs ist die Anmeldung nicht geöffnet.');
            }
            $deadline = $course->getRegistrationDeadline();
            if ($deadline !== null && $deadline->format('Y-m-d') < (new \DateTimeImmutable('today'))->format('Y-m-d')) {
                throw new \DomainException('Die Anmeldefrist ist abgelaufen.');
            }
            $person = $attendee instanceof Member ? $attendee->getPerson() : $attendee;
            if ($person === null || $person->getId() === null || $course->getClub() === null) {
                throw new \DomainException('Person oder Kursverein fehlt.');
            }
            $member = $this->em->getRepository(Member::class)->findOneBy(['person' => $person, 'club' => $course->getClub()]);
            $existing = $this->em->getRepository(CourseParticipant::class)->findOneBy(['course' => $course, 'person' => $person]);
            if ($existing !== null) {
                throw new \DomainException('Du bist für diesen Kurs bereits registriert.');
            }
            // COUNT query, not a potentially stale Doctrine collection.
            $count = $this->occupiedPlaces($course);
            $max = $course->getMaxParticipants();
            $registration = (new CourseParticipant())
                ->setCourse($course)
                ->setMember($member)
                ->setPerson($person)
                ->setFeeCents(self::moneyToCents($course->getCourseFee()))
                ->setStatus($max !== null && $count >= $max ? CourseParticipantStatus::WAITLISTED : CourseParticipantStatus::REGISTERED);
            $this->em->persist($registration);
            $course->addParticipant($registration);
            $this->em->flush();
            return $registration;
        });
    }

    public static function moneyToCents(string $amount): int
    {
        if (!preg_match('/^-?(?:0|[1-9]\d{0,7})(?:[.,]\d{1,2})?$/D', $amount)) {
            throw new \DomainException('Bitte einen gültigen Eurobetrag mit höchstens zwei Nachkommastellen eingeben.');
        }
        $negative = str_starts_with($amount, '-');
        $parts = explode('.', str_replace(',', '.', ltrim($amount, '-')));
        $cents = (int) $parts[0] * 100 + (int) str_pad($parts[1] ?? '', 2, '0');
        return $negative ? -$cents : $cents;
    }

    public function cancel(CourseParticipant $participant): void
    {
        $this->em->getConnection()->transactional(function () use ($participant): void {
            $course = $participant->getCourse();
            if ($course === null) { throw new \DomainException('Kurs fehlt.'); }
            $this->em->lock($course, LockMode::PESSIMISTIC_WRITE);
            $this->em->refresh($course);
            $this->em->refresh($participant);
            if (!in_array($participant->getStatus(), [CourseParticipantStatus::REGISTERED, CourseParticipantStatus::ACTIVE, CourseParticipantStatus::WAITLISTED], true)
                || $course->getStatus() === CourseStatus::COMPLETED || $participant->getArchivedAt() !== null) {
                throw new \DomainException('Diese Anmeldung kann nicht mehr storniert werden.');
            }
            $wasWaiting = $participant->getStatus() === CourseParticipantStatus::WAITLISTED;
            $participant->setStatus(CourseParticipantStatus::CANCELLED);
            $this->em->flush();
            if (!$wasWaiting && $course->getStatus() === CourseStatus::OPEN
                && ($course->getMaxParticipants() === null || $this->occupiedPlaces($course) < $course->getMaxParticipants())) {
                $next = $this->em->getRepository(CourseParticipant::class)->findOneBy(
                    ['course' => $course, 'status' => CourseParticipantStatus::WAITLISTED],
                    ['registeredAt' => 'ASC', 'id' => 'ASC']
                );
                $next?->setStatus(CourseParticipantStatus::REGISTERED);
            }
            $this->em->flush();
        });
    }

    public function paidCents(CourseParticipant $participant): int
    {
        return (int) $this->em->createQueryBuilder()->select('COALESCE(SUM(p.amountCents), 0)')
            ->from(CoursePayment::class, 'p')->where('p.participant = :participant')
            ->setParameter('participant', $participant)->getQuery()->getSingleScalarResult();
    }

    private function occupiedPlaces(Courses $course): int
    {
        return (int) $this->em->createQueryBuilder()->select('COUNT(cp.id)')
            ->from(CourseParticipant::class, 'cp')->where('cp.course = :course')
            ->andWhere('cp.status NOT IN (:excluded)')->setParameter('course', $course)
            ->setParameter('excluded', [CourseParticipantStatus::CANCELLED->value, CourseParticipantStatus::WAITLISTED->value])
            ->getQuery()->getSingleScalarResult();
    }

    public function recordPayment(CourseParticipant $participant, int $amount, string $method, string $reference, string $actor): void
    {
        $this->em->getConnection()->transactional(function () use ($participant, $amount, $method, $reference, $actor): void {
            $this->em->lock($participant, LockMode::PESSIMISTIC_WRITE);
            $this->em->refresh($participant);
            if ($this->em->getRepository(CoursePayment::class)->findOneBy(['participant' => $participant, 'reference' => trim($reference)]) !== null) {
                throw new \DomainException('Diese Belegreferenz wurde bereits gebucht.');
            }
            $paid = $this->paidCents($participant);
            if ($participant->getStatus() === CourseParticipantStatus::WAITLISTED
                || ($participant->getStatus() === CourseParticipantStatus::CANCELLED && $amount > 0)
                || $paid + $amount < 0 || $paid + $amount > $participant->getFeeCents()) {
                throw new \DomainException('Buchung nicht möglich: Status, offener Betrag oder Erstattung prüfen.');
            }
            $this->em->persist(new CoursePayment($participant, $amount, $method, $reference, $actor));
            $this->em->flush();
        });
    }
}
