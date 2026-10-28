<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Booking;
use App\Entity\CourseParticipant;
use App\Entity\CoursePayment;
use App\Entity\Courses;
use App\Entity\CourseTrainingUnit;
use App\Entity\Member;
use App\Entity\Schedule;
use App\Entity\TrainingUnitType;
use App\Entity\User;
use App\Enum\CourseParticipantStatus;
use App\Enum\CourseStatus;
use App\Form\TrainingCourseType;
use App\Security\Voter\CourseManagementVoter;
use App\Security\Voter\TrainingAssessmentVoter;
use App\Service\CourseCompletionService;
use App\Service\CourseArchiveService;
use App\Service\FrontendCourseRegistrationService;
use App\Service\ScheduleParticipantSyncService;
use App\Service\TrainingProgressService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints as Assert;

#[IsGranted('ROLE_USER')]
final class TrainingPortalController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly FrontendCourseRegistrationService $registration,
        private readonly TrainingProgressService $progress,
    ) {}

    #[Route('/training', name: 'training_portal', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $user = $this->account();
        $mine = $user->getPerson() === null ? [] : $this->em->getRepository(CourseParticipant::class)->findBy(['person' => $user->getPerson()], ['id' => 'DESC']);
        $staffCourses = [];
        $club = $user->getMember()?->getClub();
        $candidates = [];
        if ($request->attributes->get('routeCreatedByEasyAdmin')) {
            $candidates = $this->isGranted('ROLE_SUPER_ADMIN')
                ? $this->em->getRepository(Courses::class)->findBy([], ['id' => 'DESC'])
                : ($club === null ? [] : $this->em->getRepository(Courses::class)->findBy(['club' => $club], ['id' => 'DESC']));
        }
        foreach ($candidates as $course) {
            if ($this->canViewCourse($course)) { $staffCourses[] = $course; }
        }
        return $this->render('training/index.html.twig', ['enrollments' => $mine, 'courses' => $staffCourses]);
    }

    #[Route('/training/course/new', name: 'training_course_new', methods: ['GET', 'POST'], priority: 10)]
    #[Route('/training/course/{id}/edit', name: 'training_course_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, ?int $id = null): Response
    {
        if ($id === null) {
            $club = $this->account()->getMember()?->getClub();
            if ($club === null) { throw $this->createAccessDeniedException('Für neue Kurse ist eine Vereinszuordnung erforderlich.'); }
            $course = (new Courses())->setClub($club);
        } else {
            $course = $this->course($id);
        }
        $this->denyAccessUnlessGranted(CourseManagementVoter::MANAGE, $course);
        $this->assertEditable($course);
        if ($redirect = $this->legacyStaffRedirect($request)) { return $redirect; }
        $form = $this->createForm(TrainingCourseType::class, $course, [
            'club' => $course->getClub(),
            'instructors' => $this->em->getRepository(Member::class)->findInstructors($course->getClub()),
        ]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($course);
            $this->em->flush();
            return $this->redirectToRoute('admin_portal_training_course', ['id' => $course->getId()]);
        }
        return $this->render('training/form.html.twig', ['form' => $form, 'title' => 'Kursverwaltung']);
    }

    #[Route('/training/course/{id}', name: 'training_course', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id, Request $request): Response
    {
        $course = $this->course($id);
        if (!$this->canViewCourse($course)) { throw $this->createAccessDeniedException(); }
        if ($redirect = $this->legacyStaffRedirect($request)) { return $redirect; }
        $rows = [];
        foreach ($course->getParticipants() as $participant) {
            $rows[] = ['participant' => $participant, 'progress' => $this->progress->calculate($participant), 'paid' => $this->registration->paidCents($participant)];
        }
        return $this->render('training/course.html.twig', ['course' => $course, 'rows' => $rows]);
    }

    #[Route('/training/course/{id}/unit', name: 'training_course_unit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function unit(int $id, Request $request): Response
    {
        $course = $this->course($id);
        $this->denyAccessUnlessGranted(CourseManagementVoter::MANAGE, $course);
        $this->assertEditable($course);
        if ($redirect = $this->legacyStaffRedirect($request)) { return $redirect; }
        $types = $this->em->getRepository(TrainingUnitType::class)->createQueryBuilder('t')
            ->where('t.club IS NULL OR t.club = :club')->setParameter('club', $course->getClub())->getQuery()->getResult();
        $unit = (new CourseTrainingUnit())->setCourse($course);
        $form = $this->createFormBuilder($unit)
            ->add('trainingUnitType', EntityType::class, ['class' => TrainingUnitType::class, 'choices' => $types, 'label' => 'Theorie-, Praxis- oder Prüfungseinheit'])
            ->add('requiredSessions', IntegerType::class, ['label' => 'Benötigte Termine', 'constraints' => [new Assert\Positive()]])
            ->add('required', CheckboxType::class, ['label' => 'Pflichteinheit', 'required' => false])
            ->add('notes', TextareaType::class, ['label' => 'Lernziele', 'required' => false, 'constraints' => [new Assert\Length(max: 255)]])->getForm();
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if ($this->em->getRepository(CourseTrainingUnit::class)->findOneBy(['course' => $course, 'trainingUnitType' => $unit->getTrainingUnitType()])) {
                $this->addFlash('warning', 'Diese Einheit ist im Kurs bereits vorhanden.');
            } else {
                $course->addTrainingUnit($unit);
                $this->em->flush();
                return $this->redirectToRoute('admin_portal_training_course', ['id' => $id]);
            }
        }
        return $this->render('training/form.html.twig', ['form' => $form, 'title' => 'Ausbildungsplan und Lernziele']);
    }

    #[Route('/training/course/{id}/unit-type', name: 'training_unit_type_new', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function unitType(int $id, Request $request): Response
    {
        $course = $this->course($id);
        $this->denyAccessUnlessGranted(CourseManagementVoter::MANAGE, $course);
        $this->assertEditable($course);
        if ($redirect = $this->legacyStaffRedirect($request)) { return $redirect; }
        $type = (new TrainingUnitType())->setClub($course->getClub());
        $form = $this->createFormBuilder($type)
            ->add('code', TextType::class, ['label' => 'Eindeutiger Code', 'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 50)]])
            ->add('name', TextType::class, ['label' => 'Theorie- / Praxiseinheit', 'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 150)]])
            ->add('description', TextareaType::class, ['label' => 'Lernzielbeschreibung', 'required' => false, 'constraints' => [new Assert\Length(max: 255)]])
            ->add('examination', CheckboxType::class, ['label' => 'Prüfungseinheit', 'required' => false])->getForm();
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if ($this->em->getRepository(TrainingUnitType::class)->findOneBy(['club' => $course->getClub(), 'code' => $type->getCode()])) {
                $this->addFlash('warning', 'Dieser Einheitencode existiert im Verein bereits.');
            } else {
                $this->em->persist($type);
                $this->em->flush();
                return $this->redirectToRoute('admin_portal_training_course_unit', ['id' => $id]);
            }
        }
        return $this->render('training/form.html.twig', ['form' => $form, 'title' => 'Theorie-, Praxis- oder Prüfungseinheit definieren']);
    }

    #[Route('/training/course/{id}/session', name: 'training_course_session', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function session(int $id, Request $request, ScheduleParticipantSyncService $sync): Response
    {
        $course = $this->course($id);
        $this->denyAccessUnlessGranted(CourseManagementVoter::MANAGE, $course);
        $this->assertEditable($course);
        if ($redirect = $this->legacyStaffRedirect($request)) { return $redirect; }
        $types = [];
        foreach ($course->getTrainingUnits() as $unit) { $types[] = $unit->getTrainingUnitType(); }
        $instructors = $this->em->getRepository(Member::class)->findInstructors($course->getClub());
        $schedule = (new Schedule())->setCourses($course)->setInstructor($course->getLeadInstructor());
        $form = $this->createFormBuilder($schedule)
            ->add('title', TextType::class, ['label' => 'Terminname', 'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 255)]])
            ->add('trainingUnitType', EntityType::class, ['class' => TrainingUnitType::class, 'choices' => $types, 'label' => 'Ausbildungseinheit'])
            ->add('startDate', DateType::class, ['label' => 'Datum', 'widget' => 'single_text'])
            ->add('startTime', TimeType::class, ['label' => 'Uhrzeit', 'widget' => 'single_text'])
            ->add('location', TextType::class, ['label' => 'Ausbildungsort', 'constraints' => [new Assert\Length(max: 100)]])
            ->add('instructor', EntityType::class, ['label' => 'Ausbilder', 'class' => Member::class, 'choices' => $instructors])
            ->add('makeupFor', EntityType::class, ['mapped' => false, 'required' => false, 'label' => 'Nachholtermin für', 'class' => Schedule::class, 'choices' => $course->getSchedule()])
            ->getForm();
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $original = $form->get('makeupFor')->getData();
            if ($original !== null && ($original->getTrainingUnitType() !== $schedule->getTrainingUnitType()
                || $original->getStartDate() > $schedule->getStartDate())) {
                $this->addFlash('warning', 'Ein Nachholtermin muss zur gleichen Einheit gehören und nach dem ursprünglichen Termin liegen.');
            } else {
                $this->em->getConnection()->transactional(function () use ($course, $schedule, $sync, $original): void {
                    $course->addSchedule($schedule);
                    $this->em->persist($schedule);
                    $this->em->flush();
                    $sync->sync($schedule);
                    if ($original !== null) {
                        foreach ($this->em->getRepository(Booking::class)->findBy(['schedule' => $schedule]) as $booking) {
                            $previous = $this->em->getRepository(Booking::class)->findOneBy(['schedule' => $original, 'courseParticipant' => $booking->getCourseParticipant()]);
                            if ($previous !== null) { $booking->setMakeupFor($previous); }
                        }
                        $this->em->flush();
                    }
                });
                return $this->redirectToRoute('admin_portal_training_course', ['id' => $id]);
            }
        }
        return $this->render('training/form.html.twig', ['form' => $form, 'title' => 'Ausbildungs- oder Nachholtermin']);
    }

    #[Route('/training/participant/{id}', name: 'training_participant', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function participant(int $id, Request $request): Response
    {
        $participant = $this->participantForViewer($id);
        if ($participant->getPerson() !== $this->account()->getPerson()
            && ($redirect = $this->legacyStaffRedirect($request))) { return $redirect; }
        return $this->render('training/participant.html.twig', [
            'participant' => $participant, 'progress' => $this->progress->calculate($participant),
            'paid' => $this->registration->paidCents($participant),
            'payments' => $this->em->getRepository(CoursePayment::class)->findBy(['participant' => $participant], ['id' => 'ASC']),
        ]);
    }

    #[Route('/training/participant/{id}/cancel', name: 'training_cancel', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function cancel(int $id, Request $request): Response
    {
        $participant = $this->participantForViewer($id);
        if ($participant->getPerson() !== $this->account()->getPerson()) {
            $this->denyAccessUnlessGranted(CourseManagementVoter::MANAGE, $participant->getCourse());
        }
        $this->token($request, 'training_cancel_' . $id);
        try {
            $this->registration->cancel($participant);
            $this->addFlash('success', 'Anmeldung storniert. Bereits gezahlte Beträge werden durch die Kursverwaltung erstattet.');
        } catch (\DomainException $e) { $this->addFlash('warning', $e->getMessage()); }
        return $this->redirectToRoute(
            $request->attributes->get('routeCreatedByEasyAdmin') ? 'admin_portal_training_participant' : 'training_participant',
            ['id' => $id]
        );
    }

    #[Route('/training/participant/{id}/payment', name: 'training_payment', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function payment(int $id, Request $request): Response
    {
        $participant = $this->participantForViewer($id);
        $this->denyAccessUnlessGranted(CourseManagementVoter::MANAGE, $participant->getCourse());
        $this->token($request, 'training_payment_' . $id);
        try {
            $this->registration->recordPayment($participant,
                FrontendCourseRegistrationService::moneyToCents($request->request->getString('amount')),
                $request->request->getString('method'), $request->request->getString('reference'),
                $this->account()->getUserIdentifier());
            $this->addFlash('success', 'Zahlung oder Erstattung wurde gebucht.');
        } catch (\DomainException $e) { $this->addFlash('warning', $e->getMessage()); }
        return $this->redirectToRoute('admin_portal_training_participant', ['id' => $id]);
    }

    #[Route('/training/participant/{id}/complete', name: 'training_complete_participant', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function completeParticipant(int $id, Request $request, CourseCompletionService $completion): Response
    {
        $participant = $this->participantForViewer($id);
        $this->denyAccessUnlessGranted(CourseManagementVoter::MANAGE, $participant->getCourse());
        if (!$this->isGranted('ROLE_INSTRUCTOR') && !$this->isGranted('ROLE_ADMIN')) { throw $this->createAccessDeniedException(); }
        $this->token($request, 'training_complete_' . $id);
        try {
            $this->assertEditable($participant->getCourse());
            $decision = $request->request->getString('decision');
            if ($decision === 'passed') {
                $brevetNumber = trim($request->request->getString('brevetNumber'));
                $completion->pass($participant, null, $brevetNumber === '' ? null : $brevetNumber);
            } elseif ($decision === 'failed' && in_array($participant->getStatus(), [CourseParticipantStatus::REGISTERED, CourseParticipantStatus::ACTIVE], true)) {
                $participant->setStatus(CourseParticipantStatus::FAILED);
                $this->em->flush();
            } else { throw new \DomainException('Ungültige Abschlussentscheidung.'); }
            $this->addFlash('success', 'Abschlussentscheidung gespeichert.');
        } catch (\LogicException $e) { $this->addFlash('warning', $e->getMessage()); }
        return $this->redirectToRoute('admin_portal_training_participant', ['id' => $id]);
    }

    #[Route('/training/course/{id}/complete', name: 'training_complete_course', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function completeCourse(int $id, Request $request, CourseArchiveService $archive): Response
    {
        $course = $this->course($id);
        $this->denyAccessUnlessGranted(CourseManagementVoter::MANAGE, $course);
        $this->token($request, 'training_close_' . $id);
        if (!$course->canBeCompleted()) {
            $this->addFlash('warning', 'Alle Teilnehmer benötigen zunächst einen abschließenden Status; auch die Warteliste muss bearbeitet sein.');
        } else {
            try {
                $archive->close($course);
                $this->addFlash('success', 'Kurs abgeschlossen und Ausbildungsnachweise archiviert.');
            } catch (\DomainException $e) { $this->addFlash('warning', $e->getMessage()); }
        }
        return $this->redirectToRoute('admin_portal_training_course', ['id' => $id]);
    }

    #[Route('/training/participant/{id}/certificate', name: 'training_certificate', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function certificate(int $id): Response
    {
        $participant = $this->participantForViewer($id);
        if (!$participant->hasPassed() || $participant->getCertificateNumber() === null || $participant->getTrainingArchive() === null) {
            throw $this->createNotFoundException('Noch keine Bescheinigung vorhanden.');
        }
        $response = $this->render('training/certificate.html.twig', ['participant' => $participant, 'archive' => $participant->getTrainingArchive()]);
        $response->headers->set('Cache-Control', 'private, no-store');
        return $response;
    }

    private function legacyStaffRedirect(Request $request): ?Response
    {
        if ($request->attributes->get('routeCreatedByEasyAdmin')) { return null; }
        return $this->redirectToRoute(
            'admin_portal_' . $request->attributes->getString('_route'),
            $request->attributes->has('id') ? ['id' => $request->attributes->get('id')] : [],
            $request->isMethod('GET') ? Response::HTTP_SEE_OTHER : Response::HTTP_TEMPORARY_REDIRECT
        );
    }

    private function account(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User || !$user->isVerified()) { throw $this->createAccessDeniedException('Bestätigtes Konto erforderlich.'); }
        return $user;
    }

    private function course(int $id): Courses
    {
        $course = $this->em->find(Courses::class, $id);
        if (!$course instanceof Courses) { throw $this->createNotFoundException('Kurs nicht gefunden.'); }
        return $course;
    }

    private function participantForViewer(int $id): CourseParticipant
    {
        $participant = $this->em->find(CourseParticipant::class, $id);
        if (!$participant instanceof CourseParticipant) { throw $this->createNotFoundException(); }
        if ($participant->getPerson() !== $this->account()->getPerson() && !$this->canViewCourse($participant->getCourse())) {
            throw $this->createAccessDeniedException();
        }
        return $participant;
    }

    private function canViewCourse(?Courses $course): bool
    {
        if ($course === null) { return false; }
        if ($this->isGranted(CourseManagementVoter::MANAGE, $course)) { return true; }
        $member = $this->account()->getMember();
        if (!$this->isGranted('ROLE_INSTRUCTOR') || $member === null || $course->getClub() !== $member->getClub()) { return false; }
        if ($course->getLeadInstructor() === $member) { return true; }
        foreach ($course->getSchedule() as $schedule) {
            if ($schedule->getInstructor() === $member) { return true; }
        }
        return false;
    }

    private function assertEditable(Courses $course): void
    {
        if (in_array($course->getStatus(), [CourseStatus::COMPLETED, CourseStatus::CANCELLED], true)) {
            throw $this->createAccessDeniedException('Abgeschlossene oder abgesagte Kurse sind gesperrt.');
        }
    }

    private function token(Request $request, string $id): void
    {
        if (!$this->isCsrfTokenValid($id, $request->request->getString('_token'))) { throw $this->createAccessDeniedException('Ungültiger CSRF-Token.'); }
    }
}
