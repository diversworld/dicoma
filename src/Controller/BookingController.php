<?php

namespace App\Controller;

use App\Entity\Booking;
use App\Entity\Schedule;
use App\Entity\Member;
use App\Entity\User;
use App\Form\BookingType;
use App\Repository\BookingRepository;
use App\Repository\ScheduleRepository;
use App\Repository\UserRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Finder\Exception\AccessDeniedException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/booking')]
class BookingController extends AbstractController
{
    #[Route('/', name: 'app_booking_index', methods: ['GET'])]
    public function index(BookingRepository $bookingRepository): Response
    {
        return $this->render('booking/index.html.twig', [
            'bookings' => $bookingRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_booking_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, ScheduleRepository $scheduleRepository, UserRepository $studentsRepository): Response
    {
        // Überprüfen, ob der Benutzer als Admin authentifiziert ist
        if (!$this->isGranted('ROLE_ADMIN')) {
            throw new AccessDeniedException('You are not authorized to perform this action.');
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
            'available_schedules' => $scheduleRepository->findAll(), // Alle verfügbaren Termine laden
            'available_students' => $studentsRepository->findByCategory('student',25), // Alle verfügbaren Schüler laden
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $entityManager->persist($booking);
            $entityManager->flush();

            return $this->redirectToRoute('app_booking_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('booking/new.html.twig', [
            'booking' => $booking,
            'form' => $form
        ]);
    }

    #[Route('/book/{id}/{user}', name: 'app_booking_book')]
    public function book_course($id, $user, EntityManagerInterface $entityManager, SessionInterface $session): RedirectResponse
    {
        // Finde den Zeitplan basierend auf der übergebenen ID
        $schedule = $entityManager->getRepository(Schedule::class)->find($id);
        if (!$schedule) {
            throw $this->createNotFoundException('No schedule found for id ' . $id);
        }

        // Überprüfen, ob der Benutzer als Admin authentifiziert ist
        if (!$this->isGranted('ROLE_USER')) {
            throw new AccessDeniedException('You are not authorized to perform this action.');
        }

        // Finde den aktuell eingeloggten Benutzer
        /** @var User $user */
        $user = $this->getUser();

        // Finde das Mitglied (Member) basierend auf der User-ID
        $member = $entityManager->getRepository(Member::class)->findOneBy(['user' => $user]);
        if (!$member) {
            throw $this->createNotFoundException('No member found for user ID ' . $user->getId());
        }

        // Überprüfen, ob bereits eine Buchung für diesen Kurs existiert
        if ($this->checkBookings($schedule, $member)) {
            $session->getFlashBag()->clear('error');  // Clear any previously set 'error' flash messages
            $this->addFlash('error', 'Du hast diesen Kurs bereits gebucht');
            return $this->redirect($this->generateUrl('app_schedule_index'));
        }

        // Aktuelles Datum
        $currentDate = new DateTime();

        // Erstellung der Buchungsnummer
        $bookingNr = BookingController::nextBookingNumber($entityManager);

        // Erstellen der neuen Buchung
        $buchung = new Booking();
        $buchung->setBookingdate($currentDate);
        $buchung->setBookingnumber($bookingNr);
        $buchung->setSchedule($schedule);
        $buchung->setStatus('gebucht');
        $buchung->setMember($member);  // Setze den Member anstelle von User

        // Speichern der Buchung
        $entityManager->persist($buchung);
        $entityManager->flush();

        // Flash-Nachricht hinzufügen
        $this->addFlash('Kurs', $schedule->getTitle() . ' wurde zur Buchung hinzugefügt');

        // Umleitung zur Buchungsübersicht
        return $this->redirect($this->generateUrl('app_booking_index'));
    }

    #[Route('/{id}', name: 'app_booking_show', methods: ['GET'])]
    public function show(Booking $booking): Response
    {
        return $this->render('booking/show.html.twig', [
            'booking' => $booking,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_booking_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Booking $booking, EntityManagerInterface $entityManager, ScheduleRepository $scheduleRepository, $id): Response
    {
        // Überprüfen, ob der Benutzer als Admin authentifiziert ist
        if (!$this->isGranted('ROLE_ADMIN')) {
            throw new AccessDeniedException('You are not authorized to perform this action.');
        }

        // Annahme: Laden der Buchung aus der Datenbank mit der ID $id
        $booking = $entityManager->getRepository(Booking::class)->find($id);

        $form = $this->createForm(BookingType::class, $booking, [
            'admin_mode' => $this->isGranted('ROLE_ADMIN'),
            'available_schedules' => $scheduleRepository->findAll(), // Alle verfügbaren Termine laden
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_booking_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('booking/edit.html.twig', [
            'form' => $form->createView(),
            'booking' => $booking, // Übergabe der Buchungsdaten an die Vorlage
        ]);
    }

    #[Route('/{id}', name: 'app_booking_delete', methods: ['POST'])]
    public function delete(Request $request, Booking $booking, EntityManagerInterface $entityManager): Response
    {
        // Überprüfen, ob der Benutzer als Admin authentifiziert ist
        if (!$this->isGranted('ROLE_ADMIN')) {
            throw new AccessDeniedException('You are not authorized to perform this action.');
        }

        if ($this->isCsrfTokenValid('delete'.$booking->getId(), $request->request->get('_token'))) {
            $entityManager->remove($booking);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_booking_index', [], Response::HTTP_SEE_OTHER);
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