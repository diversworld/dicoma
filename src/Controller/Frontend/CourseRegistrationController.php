<?php

declare(strict_types=1);

namespace App\Controller\Frontend;

use App\Entity\CourseParticipant;
use App\Entity\Courses;
use App\Entity\Member;
use App\Entity\Person;
use App\Entity\User;
use App\Enum\CourseStatus;
use App\Service\FrontendCourseRegistrationService;
use App\Service\SingleClubMode;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class CourseRegistrationController extends AbstractController
{
    #[Route('/courses/', name: 'app_courses_index', methods: ['GET'])]
    public function catalog(EntityManagerInterface $em, SingleClubMode $singleClub, LoggerInterface $logger): Response
    {
        $user = $this->getUser();
        $person = $user instanceof User ? $user->getPerson() : null;
        $club = $singleClub->optionalClub();
        $courses = $club === null ? [] : $em->getRepository(Courses::class)
            ->findBy(['status' => CourseStatus::OPEN, 'club' => $club], ['id' => 'DESC']);
        $registrations = $person === null ? [] : $em->getRepository(CourseParticipant::class)->findBy(['person' => $person]);
        $registered = [];
        foreach ($registrations as $registration) {
            $id = $registration->getCourse()?->getId();
            if ($id !== null) {
                $registered[$id] = true;
            }
        }
        $instructors = [];
        if ($club !== null) {
            $rows = $em->createQueryBuilder()
                ->select('c.id AS courseId', 'IDENTITY(c.leadInstructor) AS instructorId', 'instructor.id AS memberId', 'person.id AS personId', 'person.firstname AS firstname', 'person.lastname AS lastname')
                ->from(Courses::class, 'c')
                ->leftJoin('c.leadInstructor', 'instructor')
                ->leftJoin('instructor.person', 'person')
                ->where('c.club = :club AND c.status = :status')
                ->setParameter('club', $club)
                ->setParameter('status', CourseStatus::OPEN)
                ->getQuery()->getArrayResult();
            foreach ($rows as $row) {
                if ($row['instructorId'] !== null && ($row['memberId'] === null || $row['personId'] === null)) {
                    $logger->warning('Kurs hat eine ungültige Ausbilderzuordnung.', ['course_id' => $row['courseId'], 'member_id' => $row['instructorId']]);
                    continue;
                }
                if ($row['personId'] !== null) {
                    $instructors[$row['courseId']] = trim(($row['firstname'] ?? '').' '.($row['lastname'] ?? ''));
                }
            }
        }
        return $this->render('student/course_registration.html.twig', [
            'courses' => $courses,
            'registered' => $registered,
            'instructors' => $instructors,
        ]);
    }

    #[Route('/my-training/courses', name: 'frontend_course_registration_index', methods: ['GET'])]
    #[Route('/my-training/courses/registrations', name: 'frontend_course_registrations', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function registrations(EntityManagerInterface $em): Response
    {
        $registrations = $em->getRepository(CourseParticipant::class)->findBy(
            ['person' => $this->currentPerson()],
            ['registeredAt' => 'DESC', 'id' => 'DESC']
        );

        return $this->render('student/course_registrations.html.twig', [
            'registrations' => $registrations,
        ]);
    }

    #[Route('/my-training/courses/confirmation/{id}', name: 'frontend_course_registration_confirmation', requirements: ['id' => '\\d+'], methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function confirmation(int $id, EntityManagerInterface $em): Response
    {
        // The member predicate is mandatory: an ID alone must never expose another registration.
        $registration = $em->getRepository(CourseParticipant::class)->findOneBy([
            'id' => $id,
            'person' => $this->currentPerson(),
        ]);
        if (!$registration instanceof CourseParticipant) {
            throw $this->createNotFoundException('Kursanmeldung nicht gefunden.');
        }

        return $this->render('student/course_registration_confirmation.html.twig', [
            'registration' => $registration,
        ]);
    }

    #[Route('/my-training/courses/{id}/register', name: 'frontend_course_register', requirements: ['id' => '\\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function register(int $id, Request $request, EntityManagerInterface $em, FrontendCourseRegistrationService $service): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('course_registration_' . $id, (string) $request->request->get('_token', ''))) {
            throw $this->createAccessDeniedException('Ungueltiger CSRF-Token.');
        }

        $person = $this->currentPerson();
        try {
            $service->register($id, $person);
            // Query the persisted registration after the service transaction commits.
            $registration = $em->getRepository(CourseParticipant::class)->findOneBy([
                'course' => $id,
                'person' => $person,
            ]);
            if (!$registration instanceof CourseParticipant || $registration->getId() === null) {
                throw new \LogicException('Die Anmeldung wurde gespeichert, konnte aber nicht geladen werden.');
            }
            return $this->redirectToRoute('frontend_course_registration_confirmation', [
                'id' => $registration->getId(),
            ]);
        } catch (\DomainException $e) {
            $this->addFlash('warning', $e->getMessage());
            return $this->redirectToRoute('app_courses_index');
        }
    }

    private function currentPerson(): Person
    {
        $user = $this->getUser();
        if (!$user instanceof User || $user->getPerson() === null || !$user->isVerified()) {
            throw $this->createAccessDeniedException('Ein bestätigtes Personenkonto ist erforderlich.');
        }
        return $user->getPerson();
    }
}
