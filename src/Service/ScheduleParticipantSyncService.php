<?php

namespace App\Service;

use App\Entity\Booking;
use App\Entity\Schedule;
use App\Enum\BookingAttendanceStatus;
use App\Enum\CourseParticipantStatus;
use App\Repository\BookingRepository;
use Doctrine\ORM\EntityManagerInterface;

class ScheduleParticipantSyncService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly BookingRepository $bookingRepository,
        private readonly BookingNumberGenerator $bookingNumberGenerator
    ) {
    }

    /**
     * Übernimmt alle noch aktiven Kursteilnehmer als
     * Terminbuchungen.
     *
     * Bereits vorhandene Buchungen werden nicht erneut angelegt.
     *
     * @return int Anzahl der neu erzeugten Terminbuchungen
     */
    public function sync(Schedule $schedule): int
    {
        $course = $schedule->getCourses();

        if ($course === null) {
            throw new \LogicException(
                'Der Kurstermin ist keinem Kurs zugeordnet.'
            );
        }

        $created = 0;

        foreach ($course->getParticipants() as $participant) {
            $member = $participant->getMember();

            /*
             * Abgebrochene Kursteilnehmer werden nicht mehr
             * für neue Termine übernommen.
             */
            if (
                !in_array($participant->getStatus(), [CourseParticipantStatus::REGISTERED, CourseParticipantStatus::ACTIVE], true)
            ) {
                continue;
            }

            /*
             * Keine Doppelbuchungen.
             */
            if (
                $this->bookingRepository->findOneBy(['schedule' => $schedule, 'courseParticipant' => $participant]) !== null
                || ($member !== null && $this->bookingRepository
                    ->existsForScheduleAndMember(
                        $schedule,
                        $member
                    ))
            ) {
                continue;
            }

            $booking = new Booking();

            $booking
                ->setSchedule($schedule)
                ->setMember($member)
                ->setCourseParticipant($participant)
                ->setBookingdate(
                    new \DateTimeImmutable('today')
                )
                ->setBookingnumber(
                    $this->bookingNumberGenerator->next()
                )
                ->setStatus('gebucht')
                ->setAttendanceStatus(
                    BookingAttendanceStatus::PLANNED
                );

            $this->entityManager->persist(
                $booking
            );

            ++$created;
        }

        $this->entityManager->flush();

        return $created;
    }
}