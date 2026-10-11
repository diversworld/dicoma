<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\CourseParticipant;
use App\Entity\Schedule;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use App\Entity\TrainingUnitResult;
use App\Enum\TrainingUnitResultStatus;
use App\Repository\ScheduleRepository;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class TrainingUnitResultCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function createEntity(string $entityFqcn): TrainingUnitResult
    {
        $result = new TrainingUnitResult();
        $request = $this->requestStack->getCurrentRequest();
        $participantId = $request?->query->get('courseParticipant');
        $scheduleId = $request?->query->get('schedule');
        if (!ctype_digit((string) $participantId) || !ctype_digit((string) $scheduleId)) {
            return $result;
        }
        $participant = $this->entityManager->find(CourseParticipant::class, (int) $participantId);
        $schedule = $this->entityManager->find(Schedule::class, (int) $scheduleId);
        if ($participant instanceof CourseParticipant && $schedule instanceof Schedule
            && $participant->getCourse()?->getId() === $schedule->getCourses()?->getId()
            && $participant->getCourse() !== null) {
            $result->setCourseParticipant($participant);
            $result->setSchedule($schedule);
        }
        return $result;
    }

    public static function getEntityFqcn(): string
    {
        return TrainingUnitResult::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Ausbildungsnachweis')
            ->setEntityLabelInPlural('Ausbildungsnachweise')
            ->setDefaultSort(['id' => 'DESC']);
    }

    public function configureAssets(Assets $assets): Assets
    {
        return $assets->addJsFile('js/training-unit-result-filter.js');
    }

    public function configureFields(string $pageName): iterable
    {
        $isForm = in_array($pageName, [Crud::PAGE_NEW, Crud::PAGE_EDIT], true);

        $participant = AssociationField::new('courseParticipant', 'Kursteilnehmer')
            ->setRequired(true);
        $schedule = AssociationField::new('schedule', 'Ausbildungstermin')
            ->setFormTypeOption('placeholder', 'Bitte Termin wählen')
            ->setRequired(true);

        if ($isForm) {
            // Attribute werden auf dem Select ausgegeben; die JS-Datei erkennt
            // die Felder ansonsten auch ueber ihren Symfony-Feldnamen.
            $participant->setFormTypeOption('attr', ['data-training-participant' => '1']);
            $schedule->setFormTypeOption('attr', ['data-training-schedule' => '1']);
        }

        yield $participant;
        yield $schedule;

        if (in_array($pageName, [Crud::PAGE_INDEX, Crud::PAGE_DETAIL], true)) {
            yield TextField::new('statusLabel', 'Bewertung');
        } else {
            yield ChoiceField::new('status', 'Bewertung')
                ->setChoices(TrainingUnitResultStatus::choices());
        }

        yield DateField::new('assessedAt', 'Bewertet am')
            ->setFormat('dd.MM.yyyy');
        yield AssociationField::new('assessor', 'Pruefer')->setRequired(false);
        yield TextareaField::new('notes', 'Bemerkungen')->hideOnIndex();
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add('courseParticipant')
            ->add('schedule')
            ->add('status')
            ->add('assessor');
    }

    /**
     * Nur Termine des Kurses des ausgewaehlten Teilnehmers liefern.
     * Die Entity-Validierung prueft die Zuordnung zusaetzlich serverseitig.
     */
    #[Route('/admin/training-unit-result/schedules', name: 'admin_training_result_schedules', methods: ['GET'])]
    public function schedulesForParticipant(
        Request $request,
        ScheduleRepository $scheduleRepository,
        \Doctrine\ORM\EntityManagerInterface $entityManager,
        AdminUrlGenerator $adminUrls
    ): JsonResponse {
        $id = filter_var($request->query->get('participant'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        if ($id === false || $id === null) {
            return $this->json(['options' => []]);
        }

        $participant = $entityManager->getRepository(CourseParticipant::class)->find($id);
        if (!$participant instanceof CourseParticipant || $participant->getCourse() === null) {
            return $this->json(['options' => []]);
        }

        $schedules = $scheduleRepository->findBy(
            ['courses' => $participant->getCourse()],
            ['startDate' => 'ASC', 'id' => 'ASC']
        );

        $existing = $entityManager->getRepository(TrainingUnitResult::class)
            ->findBy(['courseParticipant' => $participant]);
        $bySchedule = [];
        foreach ($existing as $result) {
            if ($result->getSchedule()?->getId() !== null) {
                $bySchedule[$result->getSchedule()->getId()] = $result;
            }
        }

        $options = [];
        $toEdit = [];
        // Bei der Bearbeitung muss der aktuelle Wert weiter auswählbar sein.
        $editId = filter_var($request->query->get('edit'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]) ?: null;
        $current = $editId ? $entityManager->find(TrainingUnitResult::class, $editId) : null;
        $currentId = $current instanceof TrainingUnitResult
            && $current->getCourseParticipant()?->getId() === $participant->getId()
            ? $current->getSchedule()?->getId() : null;

        foreach ($schedules as $schedule) {
            $id = $schedule->getId();
            if ($id === null) {
                continue;
            }
            $label = sprintf('%s%s', (string) $schedule,
                $schedule->getStartDate() ? ' (' . $schedule->getStartDate()->format('d.m.Y') . ')' : '');
            $result = $bySchedule[$id] ?? null;
            if ($result === null || $id === $currentId) {
                $options[] = ['value' => (string) $id, 'text' => $label];
                continue;
            }
            // Bereits erfasste Nachweise niemals erneut anlegen.
            // Offene und fehlgeschlagene Ergebnisse können bearbeitet werden.
            if ($result->getStatus() !== TrainingUnitResultStatus::PASSED) {
                $toEdit[] = [
                    'text' => $label,
                    'status' => $result->getStatus()->label(),
                    'url' => (clone $adminUrls)->unsetAll()
                        ->setController(self::class)
                        ->setAction(Crud::PAGE_EDIT)
                        ->setEntityId($result->getId())
                        ->generateUrl(),
                ];
            }
        }

        return $this->json(['options' => $options, 'existing' => $toEdit]);
    }
}
