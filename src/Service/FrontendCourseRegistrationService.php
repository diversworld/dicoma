<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\CourseParticipant;
use App\Entity\Courses;
use App\Entity\Member;
use App\Enum\CourseParticipantStatus;
use App\Enum\CourseStatus;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

final class FrontendCourseRegistrationService
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    /** @throws \DomainException */
    public function register(int $courseId, Member $member): void
    {
        $this->em->wrapInTransaction(function () use ($courseId, $member): void {
            // Lock the course row to serialize registrations competing for the final place.
            $course = $this->em->find(Courses::class, $courseId, LockMode::PESSIMISTIC_WRITE);
            if (!$course instanceof Courses) {
                throw new \DomainException('Der Kurs wurde nicht gefunden.');
            }
            if ($course->getStatus() !== CourseStatus::OPEN) {
                throw new \DomainException('Für diesen Kurs ist die Anmeldung nicht geöffnet.');
            }
            $deadline = $course->getRegistrationDeadline();
            if ($deadline !== null && $deadline->format('Y-m-d') < (new \DateTimeImmutable('today'))->format('Y-m-d')) {
                throw new \DomainException('Die Anmeldefrist ist abgelaufen.');
            }
            if ($course->getClub() === null || $member->getClub() === null || $course->getClub()->getId() !== $member->getClub()->getId()) {
                throw new \DomainException('Eine Anmeldung ist nur für Mitglieder des Kursvereins möglich.');
            }
            $existing = $this->em->getRepository(CourseParticipant::class)->findOneBy(['course' => $course, 'member' => $member]);
            if ($existing !== null) {
                throw new \DomainException('Du bist für diesen Kurs bereits registriert.');
            }
            // COUNT query, not a potentially stale Doctrine collection.
            $count = (int) $this->em->createQueryBuilder()
                ->select('COUNT(cp.id)')
                ->from(CourseParticipant::class, 'cp')
                ->where('cp.course = :course')
                ->andWhere('cp.status != :cancelled')
                ->setParameter('course', $course)
                ->setParameter('cancelled', CourseParticipantStatus::CANCELLED)
                ->getQuery()->getSingleScalarResult();
            $max = $course->getMaxParticipants();
            if ($max !== null && $count >= $max) {
                throw new \DomainException('Dieser Kurs ist bereits ausgebucht.');
            }
            $registration = (new CourseParticipant())
                ->setCourse($course)
                ->setMember($member);
            $this->em->persist($registration);
        });
    }
}
