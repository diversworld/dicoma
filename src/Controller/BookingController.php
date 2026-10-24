<?php

namespace App\Controller;

use App\Entity\Booking;
use App\Controller\Admin\BookingCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use App\Entity\Schedule;
use App\Entity\Member;
use App\Entity\User;
use App\Entity\CourseParticipant;
use App\Enum\CourseParticipantStatus;
use App\Enum\CourseStatus;
use App\Enum\BookingAttendanceStatus;
use App\Service\BookingNumberGenerator;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Doctrine\DBAL\LockMode;
use App\Form\BookingType;
use App\Repository\BookingRepository;
use App\Repository\ScheduleRepository;
use App\Repository\UserRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/booking')]
class BookingController extends AbstractController
{
    #[Route('/', name: 'app_booking_index', methods: ['GET'])]
    public function index(BookingRepository $bookingRepository, AdminUrlGenerator $adminUrlGenerator): Response
    {
        // Die globale Buchungsliste ist ausschliesslich fuer Administratoren.
        // Mitglieder gelangen zur eigenen, serverseitig gefilterten Ansicht.
        if (!$this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('app_my_training_attendance');
        }

        return $this->redirect(
            (clone $adminUrlGenerator)->unsetAll()
                ->setController(BookingCrudController::class)
                ->setAction(Crud::PAGE_INDEX)
                ->generateUrl()
        );
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/new', name: 'app_booking_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, ScheduleRepository $scheduleRepository, UserRepository $studentsRepository): Response
    {
        // Überprüfen, ob der Benutzer als Admin authentifiziert ist
        if (!$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Keine Berechtigung.');
        }

        // Aktuelles Datum
        $currentDate = new DateTime();
        $monthYear = $currentDate->format('Ym'); // Monat und Jahr der Buchung
        $monthYearInt = intval($monthYear); // Monat und Jahr als Integer

        // Buchungsnummer für den aktuellen Monat und Jahr erhalten
        $lastBooking = $entityManager->getRepository(Booking::class)->findOneBy([], ['id' => 'DESC']);
        $lastBookingMonthYear = $lastBooking ? $lastBooking->getBookingnumber() : null;

        if ($lastBookingMonthYear !== null) {
            // Wenn bereits Buchungen für den aktuellen Monat existieren
            // Extrahiere die Nummer und erhöhe sie um 1
            $lastBookingNumber = intval(substr($lastBookingMonthYear, 6)); // Die letzten 4 Zeichen sind die Nummer
            $bookingNumber = $lastBookingNumber + 1;
        } else {
            // Wenn es keine Buchungen für den aktuellen Monat gibt, starte die Nummerierung von 1
            $bookingNumber = 1;
        }

        // Generiere die Buchungsnummer mit führenden Nullen
        $bookingNumberPadded = str_pad($bookingNumber, 4, '0', STR_PAD_LEFT);
        $bookingNr = $monthYearInt . $bookingNumberPadded; // Buchungsnummer in der Form "2021120001

        $booking = new Booking();

        $booking->setBookingnumber($bookingNr);
        $booking->setBookingdate($currentDate);
        $booking->setStatus('gebucht');

        $form = $this->createForm(BookingType::class, $booking, [
            'admin_mode' => $this->isGranted('ROLE_ADMIN'),
            'available_schedules' => $this->allowedSchedules($entityManager),
            'available_students' => $this->allowedMembers($entityManager),
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->assertBookingAccess($booking);

            $entityManager->persist($booking);
            $entityManager->flush();

            return $this->redirectToRoute('app_booking_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('booking/new.html.twig', [
            'booking' => $booking,
            'form' => $form
        ]);
    }

    /**
     * Legacy route kept for old bookmarks. It must never change data via GET.
     */
    #[Route('/book/{id}/{user}', name: 'app_booking_book_legacy', methods: ['GET'])]
    public function legacyBook(): Response
    {
        return new Response('Diese Buchungsadresse ist veraltet. Bitte die Terminseite neu laden.', Response::HTTP_GONE);
    }

    /**
     * Self-service booking for an already registered course participant.
     * No user/member identifier is accepted from the request.
     */
    #[IsGranted('ROLE_MEMBER')]
    #[Route('/book/{id}', name: 'app_booking_book', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function bookCourse(
        int $id,
        Request $request,
        EntityManagerInterface $entityManager,
        BookingNumberGenerator $bookingNumberGenerator
    ): RedirectResponse {
        if (!$this->isCsrfTokenValid('book_schedule_' . $id, (string) $request->request->get('_token', ''))) {
            throw $this->createAccessDeniedException('Ungueltiger CSRF-Token.');
        }

        $user = $this->getUser();
        if (!$user instanceof User || !$user->getMember() instanceof Member) {
            throw $this->createAccessDeniedException('Keine Mitgliedszuordnung vorhanden.');
        }
        $member = $user->getMember();

        // A row lock on the schedule serializes bookings for the same appointment.
        // The unique database constraint remains the final duplicate protection.
        $connection = $entityManager->getConnection();
        $connection->beginTransaction();
        try {
            $schedule = $entityManager->find(Schedule::class, $id, LockMode::PESSIMISTIC_WRITE);
            if (!$schedule instanceof Schedule || $schedule->getCourses() === null) {
                throw $this->createNotFoundException('Kurstermin nicht gefunden.');
            }
            $course = $schedule->getCourses();
            if ($course->getStatus() === CourseStatus::COMPLETED) {
                throw $this->createAccessDeniedException('Dieser Kurs ist abgeschlossen.');
            }
            $registration = $entityManager->getRepository(CourseParticipant::class)->findOneBy([
                'course' => $course, 'member' => $member,
            ]);
            if (!$registration instanceof CourseParticipant || !in_array(
                $registration->getStatus(),
                [CourseParticipantStatus::REGISTERED, CourseParticipantStatus::ACTIVE],
                true
            )) {
                throw $this->createAccessDeniedException('Keine aktive Kursanmeldung vorhanden.');
            }
            if ($course->getClub()?->getId() !== $member->getClub()?->getId()) {
                throw $this->createAccessDeniedException('Der Termin gehoert nicht zu deinem Verein.');
            }
            $existing = $entityManager->getRepository(Booking::class)->findOneBy([
                'schedule' => $schedule, 'member' => $member,
            ]);
            if ($existing === null) {
                $booking = (new Booking())
                    ->setSchedule($schedule)
                    ->setMember($member)
                    ->setBookingdate(new \DateTimeImmutable('today'))
                    ->setBookingnumber($bookingNumberGenerator->next())
                    ->setStatus('gebucht')
                    ->setAttendanceStatus(BookingAttendanceStatus::PLANNED);
                $entityManager->persist($booking);
                $entityManager->flush();
                $this->addFlash('success', 'Terminbuchung wurde angelegt.');
            } else {
                $this->addFlash('info', 'Du bist fuer diesen Termin bereits gebucht.');
            }
            $connection->commit();
        } catch (\Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }
        return $this->redirectToRoute('app_schedule_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}', name: 'app_booking_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Booking $booking): Response
    {
        if (!$this->isGranted('ROLE_ADMIN')) {
            $user = $this->getUser();
            if (!$user instanceof User
                || $user->getMember()?->getId() === null
                || $booking->getMember()?->getId() !== $user->getMember()->getId()
            ) {
                throw $this->createNotFoundException('Buchung nicht gefunden.');
            }
        }

        if ($this->isGranted('ROLE_ADMIN')) {
            $this->assertBookingAccess($booking);
        }

        return $this->render('booking/show.html.twig', [
            'booking' => $booking,
        ]);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/{id}/edit', name: 'app_booking_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Booking $booking, EntityManagerInterface $entityManager, ScheduleRepository $scheduleRepository, $id): Response
    {
        $this->assertBookingAccess($booking);

        $form = $this->createForm(BookingType::class, $booking, [
            'admin_mode' => true,
            'available_schedules' => $this->allowedSchedules($entityManager),
            'available_students' => $this->allowedMembers($entityManager),
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->assertBookingAccess($booking);
            $entityManager->flush();

            return $this->redirectToRoute('app_booking_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('booking/edit.html.twig', [
            'form' => $form->createView(),
            'booking' => $booking, // Übergabe der Buchungsdaten an die Vorlage
        ]);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/{id}', name: 'app_booking_delete', methods: ['POST'])]
    public function delete(Request $request, Booking $booking, EntityManagerInterface $entityManager): Response
    {
        $this->assertBookingAccess($booking);

        if ($this->isCsrfTokenValid('delete'.$booking->getId(), $request->request->get('_token'))) {
            $entityManager->remove($booking);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_booking_index', [], Response::HTTP_SEE_OTHER);
    }

    /**
     * Prüft sowohl den Kursverein als auch den Verein des gebuchten Mitglieds.
     * Superadministratoren behalten den globalen Zugriff.
     */
    private function assertBookingAccess(Booking $booking): void
    {
        if ($this->isGranted('ROLE_SUPER_ADMIN')) {
            return;
        }

        $clubId = $this->currentAdminClubId();
        $scheduleClubId = $booking->getSchedule()?->getCourses()?->getClub()?->getId();
        $memberClubId = $booking->getMember()?->getClub()?->getId();

        if ($scheduleClubId !== $clubId || $memberClubId !== $clubId) {
            throw $this->createAccessDeniedException('Diese Buchung gehört nicht zu deinem Verein.');
        }
    }

    private function currentAdminClubId(): int
    {
        $user = $this->getUser();
        if (!$this->isGranted('ROLE_ADMIN') || !$user instanceof User) {
            throw $this->createAccessDeniedException('Administratorberechtigung erforderlich.');
        }

        $clubId = $user->getMember()?->getClub()?->getId();
        if ($clubId === null) {
            throw $this->createAccessDeniedException('Kein Verein zugeordnet.');
        }

        return $clubId;
    }

    /** @return list<Schedule> */
    private function allowedSchedules(EntityManagerInterface $em): array
    {
        if ($this->isGranted('ROLE_SUPER_ADMIN')) {
            return $em->getRepository(Schedule::class)->findAll();
        }

        return $em->createQueryBuilder()
            ->select('s')->from(Schedule::class, 's')
            ->join('s.courses', 'c')
            ->join('c.club', 'club')
            ->where('club.id = :clubId')
            ->setParameter('clubId', $this->currentAdminClubId())
            ->getQuery()->getResult();
    }

    /** @return list<Member> */
    private function allowedMembers(EntityManagerInterface $em): array
    {
        if ($this->isGranted('ROLE_SUPER_ADMIN')) {
            return $em->getRepository(Member::class)->findAll();
        }

        return $em->createQueryBuilder()
            ->select('m')->from(Member::class, 'm')
            ->join('m.club', 'club')
            ->where('club.id = :clubId')
            ->setParameter('clubId', $this->currentAdminClubId())
            ->getQuery()->getResult();
    }

    public static function  nextBookingNumber(EntityManagerInterface $entityManager): string
    {
        // Aktuelles Datum
        $currentDate = new DateTime();

        $monthYear = $currentDate->format('Ym'); // Monat und Jahr der Buchung
        $monthYearInt = intval($monthYear); // Monat und Jahr als Integer

        // Buchungsnummer für den aktuellen Monat und Jahr erhalten
        $lastBooking = $entityManager->getRepository(Booking::class)->findOneBy([], ['id' => 'DESC']);
        $lastBookingMonthYear = $lastBooking ? $lastBooking->getBookingnumber() : null;

        if ($lastBookingMonthYear !== null && str_contains($lastBookingMonthYear, $monthYear)) {
            // Wenn bereits Buchungen für den aktuellen Monat existieren
            // Extrahiere die Nummer und erhöhe sie um 1
            $lastBookingNumber = intval(substr($lastBookingMonthYear, 6)); // Die letzten 4 Zeichen sind die Nummer
            $bookingNumber = $lastBookingNumber + 1;
        } else {
            // Wenn es keine Buchungen für den aktuellen Monat gibt, starte die Nummerierung von 1
            $bookingNumber = 1;
        }

        // Generiere die Buchungsnummer mit führenden Nullen
        $bookingNumberPadded = str_pad($bookingNumber, 4, '0', STR_PAD_LEFT);

        return $monthYearInt . $bookingNumberPadded;
    }

        private function checkBookings(
                Schedule $schedule,
                Member $member
        ): bool {
                foreach ($schedule->getBookings() as $booking) {
                        if ($booking->getMember() === $member) {
                                return true;
                        }
                }

                return false;
        }
}