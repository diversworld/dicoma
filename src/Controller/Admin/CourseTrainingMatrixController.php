<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Courses;
use App\Entity\CourseParticipant;
use App\Entity\TrainingUnitResult;
use App\Repository\CourseParticipantRepository;
use App\Repository\ScheduleRepository;
use App\Repository\TrainingUnitResultRepository;
use App\Service\TrainingProgressService;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class CourseTrainingMatrixController extends AbstractController
{
    #[Route('/admin/course-training-matrix/{id}', name: 'admin_course_training_matrix', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(
        int $id,
        EntityManagerInterface $em,
        CourseParticipantRepository $participants,
        ScheduleRepository $schedules,
        TrainingUnitResultRepository $results,
        TrainingProgressService $progress,
        AdminUrlGenerator $adminUrls
    ): Response {
        $course = $em->getRepository(Courses::class)->find($id);
        if (!$course instanceof Courses) {
            throw $this->createNotFoundException('Kurs nicht gefunden.');
        }

        $requirements = [];
        foreach ($course->getTrainingUnits() as $unit) {
            $type = $unit->getTrainingUnitType();
            if ($type?->getId() === null) {
                continue;
            }
            $requirements[] = [
                'id' => $type->getId(),
                'name' => (string) $type,
                'target' => $unit->getRequiredSessions(),
                'required' => $unit->isRequired(),
            ];
        }
        $courseSchedules = $schedules->findBy(['courses' => $course], ['startDate' => 'ASC', 'id' => 'ASC']);
        // Group actual Schedule records. Dates come exclusively from Schedule::getStartDate().
        $scheduleGroups = [];
        foreach ($courseSchedules as $schedule) {
            $type = $schedule->getTrainingUnitType();
            $name = mb_strtolower(trim(($type?->getName() ?? '') . ' ' . ($schedule->getTitle() ?? '')));
            if (str_contains($name, 'prüfung') || str_contains($name, 'pruefung') || $type?->isExamination()) {
                $group = 'Prüfungen';
            } elseif (str_contains($name, 'theorie')) {
                $group = 'Theorie';
            } elseif (str_contains($name, 'schwimm') || str_contains($name, 'pool')) {
                $group = 'Schwimmbad';
            } elseif (str_contains($name, 'freiwasser') || str_contains($name, 'open water')) {
                $group = 'Freiwasser';
            } else {
                $group = 'Weitere Einheiten';
            }
            $scheduleGroups[$group][] = [
                'schedule' => $schedule,
                'date' => $schedule->getStartDate(),
                'time' => $schedule->getStartTime(),
                'url' => $this->generateUrl('admin_training_results_batch', ['id' => $schedule->getId()]),
            ];
        }
        $orderedGroups = [];
        foreach (['Theorie', 'Prüfungen', 'Schwimmbad', 'Freiwasser', 'Weitere Einheiten'] as $name) {
            if (!empty($scheduleGroups[$name])) {
                $orderedGroups[$name] = $scheduleGroups[$name];
            }
        }
        $scheduleByType = [];
        foreach ($courseSchedules as $schedule) {
            $typeId = $schedule->getTrainingUnitType()?->getId();
            if ($typeId !== null) {
                $scheduleByType[$typeId][] = $schedule;
            }
        }

        $rows = [];
        foreach ($participants->findBy(['course' => $course], ['id' => 'ASC']) as $participant) {
            if (!$participant instanceof CourseParticipant) {
                continue;
            }
            $summary = $progress->calculate($participant);
            $byName = [];
            foreach ($summary['requirements'] as $item) {
                $byName[$item['name']] = $item;
            }
            $existing = [];
            foreach ($results->findBy(['courseParticipant' => $participant]) as $result) {
                if ($result instanceof TrainingUnitResult && $result->getSchedule()?->getId() !== null) {
                    $existing[$result->getSchedule()->getId()] = true;
                }
            }
            $cells = [];
            foreach ($requirements as $requirement) {
                $item = $byName[$requirement['name']] ?? null;
                $nextSchedule = null;
                foreach ($scheduleByType[$requirement['id']] ?? [] as $schedule) {
                    if (!isset($existing[$schedule->getId()])) {
                        $nextSchedule = $schedule;
                        break;
                    }
                }
                $newUrl = null;
                if ($nextSchedule !== null) {
                    $newUrl = (clone $adminUrls)->unsetAll()
                        ->setController(TrainingUnitResultCrudController::class)
                        ->setAction(Crud::PAGE_NEW)
                        ->set('courseParticipant', $participant->getId())
                        ->set('schedule', $nextSchedule->getId())
                        ->generateUrl();
                }
                $cells[] = [
                    'credited' => $item['credited'] ?? 0,
                    'target' => $requirement['target'],
                    'missing' => $item['missing'] ?? $requirement['target'],
                    'newUrl' => $newUrl,
                ];
            }
            $rows[] = [
                'participant' => $participant,
                'progress' => $summary,
                'cells' => $cells,
                'detailUrl' => $this->generateUrl('admin_training_progress', ['id' => $participant->getId()]),
            ];
        }

        return $this->render('admin/training_progress/matrix.html.twig', [
            'course' => $course,
            'requirements' => $requirements,
            'rows' => $rows,
            'scheduleGroups' => $orderedGroups,
        ]);
    }
}
