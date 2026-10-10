<?php

namespace App\Service;

use App\Entity\Booking;
use App\Entity\Schedule;
use App\Enum\BookingAttendanceStatus;
use App\Repository\BookingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class MakeupBookingService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly BookingRepository $bookingRepository,
        private readonly BookingNumberGenerator $bookingNumberGenerator,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function create(
        Booking $originalBooking,
        Schedule $targetSchedule
    ): Booking {
        if (!$originalBooking->requiresMakeup()) {
            throw new \LogicException(
                'Für diese Buchung ist kein Nachholtermin erforderlich.'
            );
        }

        $member = $originalBooking->getMember();

        if ($member === null) {
            throw new \LogicException(
                'Der ursprünglichen Buchung ist kein Mitglied zugeordnet.'
            );
        }

        if (
            $originalBooking->getSchedule() === $targetSchedule
        ) {
            throw new \LogicException(
                'Der Nachholtermin muss sich vom ursprünglichen '
                . 'Kurstermin unterscheiden.'
            );
        }

        /*
         * Beide Termine müssen zum gleichen Kurs gehören.
         */
        $originalCourse = $originalBooking
            ->getSchedule()
            ?->getCourses();

        $targetCourse = $targetSchedule
            ->getCourses();

        if (
            $originalCourse === null
            || $targetCourse === null
            || $originalCourse !== $targetCourse
        ) {
            throw new \LogicException(
                'Der Nachholtermin muss zum selben Kurs gehören.'
            );
        }

        /*
         * Prüfen, ob bereits eine Nachholbuchung existiert.
         */
        if (!$originalBooking->getMakeupBookings()->isEmpty()) {
            throw new \LogicException(
                'Für diese Buchung wurde bereits ein '
                . 'Nachholtermin angelegt.'
            );
        }

        /*
         * Auch auf dem Zieltermin darf das Mitglied noch
         * nicht gebucht sein.
         */
        if (
            $this->bookingRepository
                ->existsForScheduleAndMember(
                    $targetSchedule,
                    $member
                )
        ) {
            throw new \LogicException(
                $this->translator->trans('makeup.already_registered', ['%member%' => $member->getFullName()])
            );
        }

        $booking = new Booking();

        $booking
            ->setMember($member)
            ->setSchedule($targetSchedule)
            ->setBookingdate(
                new \DateTimeImmutable('today')
            )
            ->setBookingnumber(
                $this->bookingNumberGenerator->next()
            )
            ->setStatus('gebucht')
            ->setAttendanceStatus(
                BookingAttendanceStatus::PLANNED
            )
            ->setMakeupFor(
                $originalBooking
            );

        $this->entityManager->persist(
            $booking
        );

        $this->entityManager->flush();

        return $booking;
    }
}