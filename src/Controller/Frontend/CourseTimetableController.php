<?php
declare(strict_types=1);
namespace App\Controller\Frontend;
use App\Entity\CourseParticipant;
use App\Entity\User;
use App\Service\CourseTimetableService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
#[IsGranted('ROLE_USER')]
final class CourseTimetableController extends AbstractController
{
    private function ownRegistration(int $id, EntityManagerInterface $em): CourseParticipant
    {
        $user = $this->getUser();
        if (!$user instanceof User || !$user->isVerified() || !$user->getPerson()) {
            throw $this->createAccessDeniedException();
        }
        $registration = $em->getRepository(CourseParticipant::class)->findOneBy([
            'id' => $id, 'person' => $user->getPerson(),
        ]);
        if (!$registration || !$registration->getCourse()?->getSchedulePublishedAt()) {
            throw $this->createNotFoundException('Terminplan noch nicht freigegeben.');
        }
        return $registration;
    }
    #[Route('/my-training/registrations/{id}/timetable', name: 'student_course_timetable', requirements: ['id'=>'\d+'], methods: ['GET'])]
    public function view(int $id, EntityManagerInterface $em, CourseTimetableService $service): Response
    {
        $registration = $this->ownRegistration($id, $em);
        return $this->render('student/course_timetable.html.twig', [
            'registration'=>$registration, 'appointments'=>$service->appointments($registration->getCourse()),
        ]);
    }
    #[Route('/my-training/registrations/{id}/timetable.csv', name: 'student_course_timetable_csv', requirements: ['id'=>'\d+'], methods: ['GET'])]
    public function download(int $id, EntityManagerInterface $em, CourseTimetableService $service): Response
    {
        $registration = $this->ownRegistration($id, $em);
        return new Response($service->csv($registration->getCourse()), 200, [
            'Content-Type'=>'text/csv; charset=UTF-8',
            'Content-Disposition'=>'attachment; filename="dicoma-terminplan-'.$id.'.csv"',
            'Cache-Control'=>'private, no-store',
        ]);
    }
}
