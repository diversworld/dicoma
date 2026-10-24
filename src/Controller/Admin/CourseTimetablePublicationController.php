<?php
declare(strict_types=1);
namespace App\Controller\Admin;
use App\Entity\Courses;
use App\Service\CourseTimetableService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
#[IsGranted('ROLE_USER')]
final class CourseTimetablePublicationController extends AbstractController
{
    #[Route('/admin/training/timetable/{id}', name:'admin_training_timetable', requirements:['id'=>'\d+'], methods:['GET'])]
    public function index(Courses $course, CourseTimetableService $service): Response
    {
        $this->denyAccessUnlessGranted('COURSE_MANAGE', $course);
        return $this->render('admin/training/timetable.html.twig', [
            'course'=>$course, 'appointments'=>$service->appointments($course),
        ]);
    }
    #[Route('/admin/training/timetable/{id}/publish', name:'admin_training_timetable_publish', requirements:['id'=>'\d+'], methods:['POST'])]
    public function publish(Courses $course, Request $request, EntityManagerInterface $em, CourseTimetableService $service): RedirectResponse
    {
        $this->denyAccessUnlessGranted('COURSE_MANAGE', $course);
        if (!$this->isCsrfTokenValid('publish_timetable_'.$course->getId(), (string)$request->request->get('_token',''))) {
            throw $this->createAccessDeniedException('CSRF token invalid.');
        }
        if ($service->appointments($course) === []) {
            $this->addFlash('warning','Es gibt noch keine datierten Kurstermine.');
        } else {
            $course->publishSchedule(); $em->flush();
            $this->addFlash('success','Terminplan für angemeldete Teilnehmer freigegeben.');
        }
        return $this->redirectToRoute('admin_training_timetable',['id'=>$course->getId()],Response::HTTP_SEE_OTHER);
    }
    #[Route('/admin/training/timetable/{id}/send', name:'admin_training_timetable_send', requirements:['id'=>'\d+'], methods:['POST'])]
    public function send(Courses $course, Request $request, MailerInterface $mailer, CourseTimetableService $service, LoggerInterface $logger): RedirectResponse
    {
        $this->denyAccessUnlessGranted('COURSE_MANAGE', $course);
        if (!$this->isCsrfTokenValid('send_timetable_'.$course->getId(), (string)$request->request->get('_token',''))) {
            throw $this->createAccessDeniedException('CSRF token invalid.');
        }
        if (!$course->getSchedulePublishedAt()) {
            $this->addFlash('warning','Terminplan zuerst freigeben.');
            return $this->redirectToRoute('admin_training_timetable',['id'=>$course->getId()],Response::HTTP_SEE_OTHER);
        }
        $appointments = $service->appointments($course);
        $lines = ['Terminplan: '.$course->getTitle(), 'Stand: '.date('d.m.Y H:i'), '',
            'Bitte beachten: Termine können sich ändern. Der aktuelle Plan ist in DICOMA abrufbar.', ''];
        foreach ($appointments as $s) {
            $lines[] = $s->getStartDate()->format('d.m.Y').' '.
                ($s->getStartTime()?->format('H:i') ?? '').' | '.
                $s->getTrainingUnitLabel().' | '.($s->getLocation() ?? '');
        }
        $text = implode("\n", $lines);
        $sent=0; $skipped=0; $failed=0; $seen=[];
        foreach ($course->getParticipants() as $registration) {
            if (!in_array($registration->getStatus()->value, ['registered','active','passed'],true)) {++$skipped;continue;}
            $email = trim((string)$registration->getPerson()?->getEmail());
            if (!filter_var($email,FILTER_VALIDATE_EMAIL)) {++$skipped;continue;}
            $key=strtolower($email);
            if (isset($seen[$key])) {continue;}
            $seen[$key]=true;
            try {
                $mailer->send((new Email())->from('info@diversworld.eu')->to($email)->subject('DICOMA Terminplan: '.$course->getTitle())->text($text));
                ++$sent;
            } catch (TransportExceptionInterface $e) {
                ++$failed;
                $logger->error('Course timetable delivery failed.', [
                    'course_id' => $course->getId(), 'participant_id' => $registration->getId(), 'exception' => $e,
                ]);
            }
        }
        $this->addFlash($failed ? 'warning':'success',
            sprintf('Versand angestoßen: %d Empfänger; %d übersprungen; %d fehlgeschlagen.', $sent,$skipped,$failed));
        return $this->redirectToRoute('admin_training_timetable',['id'=>$course->getId()],Response::HTTP_SEE_OTHER);
    }
}
