<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Schedule;
use App\Entity\Booking;
use App\Enum\BookingAttendanceStatus;
use App\Service\TrainingAssessmentService;
use App\Service\ScheduleParticipantSyncService;
use App\Entity\TrainingUnitResult;
use App\Enum\TrainingUnitResultStatus;
use App\Repository\CourseParticipantRepository;
use App\Repository\TrainingUnitResultRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Security\Voter\TrainingAssessmentVoter;

#[IsGranted('ROLE_INSTRUCTOR')]
final class TrainingUnitBatchController extends AbstractController
{
    #[Route('/admin/training-results/batch/{id}', name: 'admin_training_results_batch', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    #[Route('/my-training/assess/{id}', name: 'instructor_training_assess', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    #[Route('/training/assess/{id}', name: 'training_assess', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    public function edit(
        int $id,
        Request $request,
        EntityManagerInterface $em,
        CourseParticipantRepository $participants,
        TrainingUnitResultRepository $results,
        TrainingAssessmentService $assessmentService,
        ScheduleParticipantSyncService $syncService
    ): Response {
        $schedule = $em->getRepository(Schedule::class)->find($id);
        if (!$schedule instanceof Schedule || $schedule->getCourses() === null) {
            throw $this->createNotFoundException('Ausbildungstermin nicht gefunden.');
        }
        $course = $schedule->getCourses();
        // Authorization applies equally to GET, assessment POST and booking sync POST.
        $this->denyAccessUnlessGranted(TrainingAssessmentVoter::ASSESS, $schedule);
        $isAdmin = $this->isGranted('ROLE_ADMIN');
        $returnRoute = $isAdmin ? 'admin_training_results_batch' : 'instructor_training_assess';

        // Explizite, CSRF-geschuetzte Synchronisierung ohne Aenderung vorhandener Buchungen.
        if ($request->isMethod('POST') && $request->request->get('operation') === 'sync_bookings') {
            if (!$this->isCsrfTokenValid('training_batch_sync_' . $id, (string) $request->request->get('_token', ''))) {
                throw $this->createAccessDeniedException('Ungueltiger CSRF-Token.');
            }
            try {
                $created = $syncService->sync($schedule);
                $this->addFlash('success', sprintf('%d fehlende Terminbuchung(en) angelegt. Bestehende Buchungen bleiben unveraendert.', $created));
            } catch (\LogicException $exception) {
                $this->addFlash('danger', $exception->getMessage());
            }
            return $this->redirectToRoute($returnRoute, ['id' => $id]);
        }
        $attendees = array_values(array_filter($participants->findBy(['course' => $course], ['id' => 'ASC']),
            static fn ($participant): bool => in_array($participant->getStatus(), [\App\Enum\CourseParticipantStatus::REGISTERED, \App\Enum\CourseParticipantStatus::ACTIVE], true)));
        $existing = [];
        foreach ($results->findBy(['schedule' => $schedule]) as $result) {
            if ($result instanceof TrainingUnitResult && $result->getCourseParticipant()?->getId() !== null) {
                $existing[$result->getCourseParticipant()->getId()] = $result;
            }
        }
        $bookings = [];
        $participantBookingsById = [];
        foreach ($em->getRepository(Booking::class)->findBy(['schedule' => $schedule]) as $booking) {
            $memberId = $booking->getMember()?->getId();
            if ($memberId !== null) { $bookings[$memberId] = $booking; }
            if ($booking->getCourseParticipant()?->getId() !== null) {
                $participantBookingsById[$booking->getCourseParticipant()->getId()] = $booking;
            }
        }
        $participantBookings = [];
        foreach ($attendees as $participant) {
            $pid = $participant->getId();
            $mid = $participant->getMember()?->getId();
            if ($pid !== null && $mid !== null && isset($bookings[$mid])) {
                $participantBookings[$pid] = $bookings[$mid];
            } elseif ($pid !== null && isset($participantBookingsById[$pid])) {
                $participantBookings[$pid] = $participantBookingsById[$pid];
            }
        }
        $errors = [];
        if ($request->isMethod('POST') && $request->request->get('operation') !== 'sync_bookings') {
            if (!$this->isCsrfTokenValid('training_batch_' . $id, (string) $request->request->get('_token', ''))) {
                throw $this->createAccessDeniedException('Ungültiger CSRF-Token.');
            }
            $submittedResults = $request->request->all('status');
            $submittedAttendance = $request->request->all('attendance');
            $changes = [];
            foreach ($attendees as $participant) {
                $pid = $participant->getId();
                if ($pid === null) { continue; }
                $rawResult = $submittedResults[(string) $pid] ?? '';
                $rawAttendance = $submittedAttendance[(string) $pid] ?? '';
                if (!is_string($rawResult) || !is_string($rawAttendance)) {
                    $errors[] = 'Ungültige Auswahl für Teilnehmer-ID ' . $pid;
                    continue;
                }
                $resultStatus = $rawResult === '' ? null : TrainingUnitResultStatus::tryFrom($rawResult);
                $attendanceStatus = $rawAttendance === '' ? null : BookingAttendanceStatus::tryFrom($rawAttendance);
                if (($rawResult !== '' && $resultStatus === null) || ($rawAttendance !== '' && $attendanceStatus === null)) {
                    $errors[] = 'Unbekannter Status für Teilnehmer-ID ' . $pid;
                    continue;
                }
                if ($resultStatus !== null || $attendanceStatus !== null) {
                    $changes[$pid] = ['result' => $resultStatus, 'attendance' => $attendanceStatus];
                }
            }
            if ($errors === []) {
                try {
                    $actor = $this->getUser();
                    $count = $assessmentService->save($schedule, $attendees, $changes, $existing, $participantBookings,
                        $actor instanceof \App\Entity\User ? $actor->getMember() : null);
                    $this->addFlash('success', sprintf('%d Teilnehmer aktualisiert.', $count));
                    return $this->redirectToRoute($returnRoute, ['id' => $id]);
                } catch (\DomainException $e) {
                    $errors[] = $e->getMessage();
                }
            }
        }
        return $this->render('admin/training_results/batch.html.twig', [
            'isAdminAssessment' => $isAdmin,
            'schedule' => $schedule,
            'course' => $course,
            'attendees' => $attendees,
            'existing' => $existing,
            'choices' => TrainingUnitResultStatus::cases(),
            'attendanceChoices' => BookingAttendanceStatus::cases(),
            'bookings' => $participantBookings,
            'errors' => $errors,
            'missingBookings' => count($attendees) - count($participantBookings),
        ]);
    }
}
