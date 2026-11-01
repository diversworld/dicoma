<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\CourseParticipant;
use App\Entity\Schedule;
use App\Entity\Member;
use App\Entity\Courses;
use App\Service\AdminClubContext;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Dto\BatchActionDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
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

#[IsGranted('ROLE_SUPER_ADMIN')]
final class TrainingUnitResultCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly EntityManagerInterface $entityManager,
        private readonly AdminClubContext $clubContext,
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
        if ($participant instanceof CourseParticipant) { $this->assertCourse($participant->getCourse()); }
        if ($schedule instanceof Schedule) { $this->assertCourse($schedule->getCourses()); }
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

    private function assertCourse(?Courses $course): void
    {
        $clubId = $this->clubContext->getClub()?->getId();
        if ($clubId === null || $course?->getClub()?->getId() !== $clubId) {
            throw $this->createAccessDeniedException('Ausbildungsnachweis gehört nicht zum ausgewählten Verein.');
        }
    }

    private function assertResult(TrainingUnitResult $result): void
    {
        $participantCourse = $result->getCourseParticipant()?->getCourse();
        $scheduleCourse = $result->getSchedule()?->getCourses();
        $this->assertCourse($participantCourse);
        $this->assertCourse($scheduleCourse);
        if ($participantCourse?->getId() !== $scheduleCourse?->getId()) {
            throw $this->createAccessDeniedException('Termin und Teilnehmer gehören nicht zum selben Kurs.');
        }
        $assessor = $result->getAssessor();
        if ($assessor !== null && $assessor->getClub()?->getId() !== $this->clubContext->getClub()?->getId()) {
            throw $this->createAccessDeniedException('Prüfer gehört nicht zum ausgewählten Verein.');
        }
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        $query = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);
        $club = $this->clubContext->getClub();
        if ($club === null) { return $query->andWhere('1 = 0'); }
        return $query->join('entity.courseParticipant', 'resultParticipant')->join('resultParticipant.course', 'resultCourse')
            ->join('entity.schedule', 'resultSchedule')->join('resultSchedule.courses', 'resultScheduleCourse')
            ->leftJoin('entity.assessor', 'resultAssessor')
            ->andWhere('resultCourse.club = :resultClub AND resultScheduleCourse = resultCourse')
            ->andWhere('resultAssessor.id IS NULL OR resultAssessor.club = :resultClub')
            ->setParameter('resultClub', $club);
    }

    public function edit(AdminContext $context)
    {
        $this->assertResult($context->getEntity()->getInstance());
        return parent::edit($context);
    }

    public function detail(AdminContext $context)
    {
        $this->assertResult($context->getEntity()->getInstance());
        return parent::detail($context);
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof TrainingUnitResult) { $this->assertResult($entityInstance); }
        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof TrainingUnitResult) {
            $original = $entityManager->getUnitOfWork()->getOriginalEntityData($entityInstance);
            $participant = $original['courseParticipant'] ?? null;
            $schedule = $original['schedule'] ?? null;
            $assessor = $original['assessor'] ?? null;
            $this->assertCourse($participant instanceof CourseParticipant ? $participant->getCourse() : null);
            $this->assertCourse($schedule instanceof Schedule ? $schedule->getCourses() : null);
            if ($assessor instanceof Member && $assessor->getClub()?->getId() !== $this->clubContext->getClub()?->getId()) {
                throw $this->createAccessDeniedException('Prüfer gehört nicht zum ausgewählten Verein.');
            }
            $this->assertResult($entityInstance);
            if (($original['courseParticipant'] ?? null)?->getArchivedAt() !== null
                || $entityInstance->getCourseParticipant()?->getArchivedAt() !== null) {
                throw $this->createAccessDeniedException('Archivierte Ausbildungsnachweise sind gesperrt.');
            }
        }
        parent::updateEntity($entityManager, $entityInstance);
    }

    public function deleteEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof TrainingUnitResult) {
            $this->assertResult($entityInstance);
            if ($entityInstance->getCourseParticipant()?->getArchivedAt() !== null) {
                throw $this->createAccessDeniedException('Archivierte Ausbildungsnachweise dürfen nicht gelöscht werden.');
            }
        }
        parent::deleteEntity($entityManager, $entityInstance);
    }

    public function batchDelete(AdminContext $context, BatchActionDto $batchActionDto): Response
    {
        if ($batchActionDto->getEntityFqcn() !== TrainingUnitResult::class) {
            throw new BadRequestHttpException('Ungültiger Ausbildungsnachweis-Stapel.');
        }
        foreach ($batchActionDto->getEntityIds() as $id) {
            if ((!is_string($id) && !is_int($id)) || !ctype_digit((string) $id) || (int) $id < 1) {
                throw new BadRequestHttpException('Ungültige Ausbildungsnachweis-ID.');
            }
            $result = $this->entityManager->find(TrainingUnitResult::class, (int) $id);
            if ($result !== null) {
                $this->assertResult($result);
                if ($result->getCourseParticipant()?->getArchivedAt() !== null) {
                    throw $this->createAccessDeniedException('Archivierte Ausbildungsnachweise dürfen nicht gelöscht werden.');
                }
            }
        }
        return parent::batchDelete($context, $batchActionDto);
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setFormOptions(['csrf_token_id' => 'training_result_'.($this->clubContext->getClub()?->getId() ?? 'none')])
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
        $participantQuery = $this->courseChoiceQuery('course');
        $scheduleQuery = $this->courseChoiceQuery('courses');
        $assessorQuery = $this->memberChoiceQuery();

        $participant = AssociationField::new('courseParticipant', 'Kursteilnehmer')
            ->setFormTypeOption('query_builder', $participantQuery)
            ->setRequired(true);
        $schedule = AssociationField::new('schedule', 'Ausbildungstermin')
            ->setFormTypeOption('query_builder', $scheduleQuery)
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
        yield AssociationField::new('assessor', 'Pruefer')->setFormTypeOption('query_builder', $assessorQuery)->setRequired(false);
        yield TextareaField::new('notes', 'Bemerkungen')->hideOnIndex();
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('courseParticipant')->setFormTypeOption('value_type_options.query_builder', $this->courseChoiceQuery('course')))
            ->add(EntityFilter::new('schedule')->setFormTypeOption('value_type_options.query_builder', $this->courseChoiceQuery('courses')))
            ->add('status')
            ->add(EntityFilter::new('assessor')->setFormTypeOption('value_type_options.query_builder', $this->memberChoiceQuery()));
    }

    private function courseChoiceQuery(string $relation): \Closure
    {
        $club = $this->clubContext->getClub();
        return static function (EntityRepository $repository) use ($relation, $club): QueryBuilder {
            $query = $repository->createQueryBuilder('choice')->join('choice.'.$relation, 'choiceCourse');
            return $club === null ? $query->andWhere('1 = 0')
                : $query->andWhere('choiceCourse.club = :club')->setParameter('club', $club);
        };
    }

    private function memberChoiceQuery(): \Closure
    {
        $club = $this->clubContext->getClub();
        return static function (EntityRepository $repository) use ($club): QueryBuilder {
            $query = $repository->createQueryBuilder('choice');
            return $club === null ? $query->andWhere('1 = 0')
                : $query->andWhere('choice.club = :club')->setParameter('club', $club);
        };
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
        $this->assertCourse($participant->getCourse());

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
