<?php

namespace App\Controller\Admin;

use App\Entity\Courses;
use App\Controller\Admin\BookingCrudController;
use App\Enum\BookingAttendanceStatus;
use App\Entity\ScheduleTemplate;
use App\Entity\ScheduleTemplateEntry;
use App\Enum\CourseStatus;
use App\Form\CourseParticipantType;
use App\Form\CourseTrainingUnitType;
use App\Form\CourseSchedulePlanningType;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use App\Security\Voter\CourseManagementVoter;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use App\Service\TrainingProgressService;
use App\Service\CourseScheduleGenerator;
use App\Service\CourseTemplateExportService;
use App\Service\CourseTrainingPlanService;
use App\Service\ScheduleConflictService;
use App\Repository\ScheduleTemplateRepository;
use App\Repository\ScheduleRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Translation\TranslatableMessage;

#[IsGranted('ROLE_ADMIN')]
class CoursesCrudController extends AbstractCrudController
{
	public function __construct(
		private readonly CourseScheduleGenerator $scheduleGenerator,
		private readonly CourseTrainingPlanService $trainingPlanService,
		private readonly TranslatorInterface $translator,
        private readonly TrainingProgressService $trainingProgress,
        private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly \App\Repository\MemberRepository $members,
        private readonly \Symfony\Component\Security\Core\Role\RoleHierarchyInterface $roleHierarchy,
	) {
	}
	
    public static function getEntityFqcn(): string
    {
        return Courses::class;
    }

    /** Paket 11: Listenansicht serverseitig auf den eigenen Verein beschraenken. */
    public function createIndexQueryBuilder(
        SearchDto $searchDto,
        EntityDto $entityDto,
        FieldCollection $fields,
        FilterCollection $filters
    ): QueryBuilder {
        $qb = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);
        if ($this->isGranted('ROLE_SUPER_ADMIN')) {
            return $qb;
        }
        $clubId = $this->getUser() instanceof \App\Entity\User
            ? $this->getUser()->getMember()?->getClub()?->getId() : null;
        if ($clubId === null) {
            $qb->andWhere('1 = 0');
        } else {
            $qb->andWhere('entity.club = :managedClub')->setParameter('managedClub', $clubId);
        }
        return $qb;
    }

    /** Paket 12: objektbezogene Autorisierung fuer alle direkten Kursaktionen. */
    private function assertCourseManage(Courses $course): void
    {
        $this->denyAccessUnlessGranted(CourseManagementVoter::MANAGE, $course);
    }

    /** Paket 13: Vereinszuordnung beim Anlegen und Bearbeiten unveraenderlich halten. */
    private function enforceCourseClub(Courses $course, ?EntityManagerInterface $em = null): void
    {
        if ($this->isGranted('ROLE_SUPER_ADMIN')) {
            return;
        }
        $club = $this->getUser() instanceof \App\Entity\User
            ? $this->getUser()->getMember()?->getClub() : null;
        if ($club === null || $club->getId() === null) {
            throw $this->createAccessDeniedException('Keine Vereinszuordnung vorhanden.');
        }
        if ($em !== null && $course->getId() !== null) {
            $original = $em->getUnitOfWork()->getOriginalEntityData($course);
            $originalClub = $original['club'] ?? null;
            if (!$originalClub instanceof \App\Entity\Club || $originalClub->getId() !== $club->getId()) {
                throw $this->createAccessDeniedException('Kurs gehoert nicht zum eigenen Verein.');
            }
        }
        $course->setClub($club);
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->overrideTemplate('crud/detail', 'admin/course/detail.html.twig')
            ->setEntityLabelInSingular('Kurs')
            ->setEntityLabelInPlural('Kurse')
            ->setPageTitle(
                Crud::PAGE_INDEX,
                'Kurse'
            )
            ->setPageTitle(
                Crud::PAGE_NEW,
                'Kurs erstellen'
            )
            ->setPageTitle(
                Crud::PAGE_EDIT,
                'Kurs bearbeiten'
            )
            ->setPageTitle(
                Crud::PAGE_DETAIL,
                'Kursdetails'
            )
            ->setDefaultSort([
                'id' => 'DESC',
            ])
            ->setSearchFields([
                'title',
                'category',
                'club.name',
                'qualification.name',
            ]);
    }

    /**
     * Die Kurszentrale wird nur auf der Detailseite mit Kennzahlen versorgt.
     * Bestehende CRUD-Aktionen und deren Berechtigungen bleiben unveraendert.
     */
    public function configureResponseParameters(KeyValueStore $responseParameters): KeyValueStore
    {
        // Paket 12: direkte Detail-/Bearbeitungs-URLs duerfen Listenfilter nicht umgehen.
        $page = $responseParameters->get('pageName');
        if (in_array($page, [Crud::PAGE_DETAIL, Crud::PAGE_EDIT], true)) {
            $candidate = $responseParameters->get('entity')?->getInstance();
            if ($candidate instanceof Courses) {
                $this->assertCourseManage($candidate);
            }
        }
        if ($responseParameters->get('pageName') !== Crud::PAGE_DETAIL) {
            return $responseParameters;
        }

        $entityDto = $responseParameters->get('entity');
        $course = $entityDto?->getInstance();
        if (!$course instanceof Courses) {
            return $responseParameters;
        }

        $participantCount = 0;
        $totalPercent = 0;
        $readyCount = 0;
        $missingCount = 0;
        foreach ($course->getParticipants() as $participant) {
            ++$participantCount;
            $progress = $this->trainingProgress->calculate($participant);
            $totalPercent += $progress['percent'];
            if ($progress['complete']) {
                ++$readyCount;
            } else {
                ++$missingCount;
            }
        }

        // Read-only booking counters, scoped to this course's schedules.
        $bookingCount = 0;
        $attendanceOpenCount = 0;
        foreach ($course->getSchedule() as $courseSchedule) {
            foreach ($courseSchedule->getBookings() as $booking) {
                ++$bookingCount;
                if ($booking->getAttendanceStatus() === BookingAttendanceStatus::PLANNED) {
                    ++$attendanceOpenCount;
                }
            }
        }
        $bookingListUrl = (clone $this->adminUrlGenerator)
            ->unsetAll()
            ->setController(BookingCrudController::class)
            ->setAction(Crud::PAGE_INDEX)
            ->set('filters', [
                'course' => ['comparison' => '=', 'value' => (string) $course->getId()],
            ])
            ->generateUrl();

        $today = new \DateTimeImmutable('today');
        $upcoming = [];
        $undatedCount = 0;
        foreach ($course->getSchedule() as $schedule) {
            $date = $schedule->getStartDate();
            if ($date === null) {
                ++$undatedCount;
                continue;
            }
            if ($date->format('Y-m-d') < $today->format('Y-m-d')) {
                continue;
            }
            $upcoming[] = [
                'id' => $schedule->getId(),
                'title' => (string) $schedule,
                'date' => $date->format('d.m.Y'),
                'time' => $schedule->getStartTime()?->format('H:i'),
                'sort' => $date->format('Y-m-d') . ' ' . ($schedule->getStartTime()?->format('H:i') ?? '00:00'),
            ];
        }
        usort($upcoming, static fn (array $a, array $b): int => strcmp($a['sort'], $b['sort']));

        // Read-only checklist: never changes course status or awards qualifications.
        $hasRequirements = !$course->getTrainingUnits()->isEmpty();
        $allParticipantStatusesFinal = $course->canBeCompleted();
        $allRequiredEvidenceComplete = $hasRequirements && $participantCount > 0 && $missingCount === 0;
        $responseParameters->set('courseCompletionChecklist', [
            'requirementsConfigured' => $hasRequirements,
            'participantsPresent' => $participantCount > 0,
            'participantStatusesFinal' => $allParticipantStatusesFinal,
            'requiredEvidenceComplete' => $allRequiredEvidenceComplete,
            'readyForReview' => $hasRequirements && $participantCount > 0
                && $allParticipantStatusesFinal && $allRequiredEvidenceComplete,
            'missingParticipants' => $missingCount,
            'undatedAppointments' => $undatedCount,
        ]);

        $responseParameters->set('courseDashboard', [
            'participantCount' => $participantCount,
            'bookingCount' => $bookingCount,
            'attendanceOpenCount' => $attendanceOpenCount,
            'bookingListUrl' => $bookingListUrl,
            'averageProgress' => $participantCount > 0 ? (int) round($totalPercent / $participantCount) : 0,
            'readyCount' => $readyCount,
            'missingCount' => $missingCount,
            'undatedCount' => $undatedCount,
            'upcoming' => array_slice($upcoming, 0, 5),
        ]);

        return $responseParameters;
    }

    private function validateLeadInstructor(Courses $course): void {
        $lead = $course->getLeadInstructor();
        if ($lead !== null && ($course->getClub() === null
            || $lead->getClub()?->getId() !== $course->getClub()->getId()
            || $lead->getUser() === null
            || !in_array(\App\Entity\User::ROLE_INSTRUCTOR, $this->roleHierarchy->getReachableRoleNames($lead->getUser()->getRoles()), true))) {
            throw new \DomainException('Kursausbilder muss ein Ausbilder des Kursvereins sein.');
        }
    }
    public function persistEntity(EntityManagerInterface $em, $entityInstance): void {
        if ($entityInstance instanceof Courses) {
            $this->enforceCourseClub($entityInstance);
            $this->validateLeadInstructor($entityInstance);
        }
        parent::persistEntity($em, $entityInstance);
    }

    public function configureFields(string $pageName): iterable
    {
        /*
         * Kursinformation
         */
        yield FormField::addFieldset('Kursinformation')
            ->collapsible();
        yield IdField::new('id', 'ID')
            ->hideOnForm()
            ->setColumns(2);
        yield AssociationField::new('club','Verein')
            ->setFormTypeOption('disabled', !$this->isGranted('ROLE_SUPER_ADMIN'))
            ->setRequired(true)
            ->setColumns(4);
        yield \EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField::new('courseFee', 'Kurspreis')
            ->setCurrency('EUR')
            ->setStoredAsCents(false)
            ->setNumDecimals(2)
            ->setRequired(true)
            ->setColumns(3)
            ->setHelp('Gesamtpreis pro Teilnehmer für diesen Kurs.');
        yield TextField::new('title','Titel')
            ->setRequired(true)
            ->setColumns(6);
        /*
         * Status
         *
         * COMPLETED darf nicht manuell gesetzt werden.
         * Dieser Zustand wird ausschließlich über
         * "Kurs abschließen" erreicht.
         */
        if ($pageName === Crud::PAGE_INDEX) {
            yield ChoiceField::new(
                'status',
                'Kursstatus'
            )
                ->setChoices(
                    CourseStatus::choices()
                );
        } else {
            $statusChoices = CourseStatus::choices();

            $course = $this
                ->getContext()
                ?->getEntity()
                ?->getInstance();

            $isCompleted =
                $course instanceof Courses
                && $course->getStatus() === CourseStatus::COMPLETED;

            /*
             * Bei einem nicht abgeschlossenen Kurs darf COMPLETED
             * nicht direkt gewählt werden.
             *
             * Bei einem bereits abgeschlossenen Kurs muss der
             * aktuelle Wert dargestellt werden können.
             */
            if (!$isCompleted) {
                unset($statusChoices['Abgeschlossen']);
            }
            yield ChoiceField::new('status','Kursstatus')
                ->setChoices($statusChoices)
                ->setColumns(3)
                ->setFormTypeOption('disabled',$isCompleted);
        }
        yield AssociationField::new('leadInstructor', 'Verantwortlicher Kursausbilder')
            ->setColumns(4)
            ->setHelp('Verantwortlicher Ausbilder; Standard fuer neue Termine.')
            ->setFormTypeOption('choices', $this->members->findInstructors(
                $this->isGranted('ROLE_SUPER_ADMIN') ? null : $this->getUser()?->getMember()?->getClub()
            ));
        yield AssociationField::new('qualification','Zielqualifikation')
            ->setRequired(false)
            ->setColumns(4)
            ->setHelp(
                'Qualifikation oder Brevet, das nach erfolgreichem '
                . 'Abschluss des Kurses erworben wird.'
            );
        /*
         * Kapazität / Anmeldung
         */
        yield IntegerField::new('participantCount','Teilnehmer')
            ->onlyOnIndex();
        yield IntegerField::new('freePlaces','Freie Plätze')
            ->onlyOnIndex();
        yield IntegerField::new('maxParticipants','Max. Teilnehmer')
            ->setColumns(3)
            ->setHelp(
                'Leer lassen, wenn die Teilnehmerzahl '
                . 'nicht begrenzt ist.'
            );
        yield DateField::new('registrationDeadline','Anmeldeschluss')
            ->setFormat('dd.MM.yyyy')
            ->setColumns(3);
        /*
         * Bild
         */
        yield FormField::addFieldset('Bild')
            ->collapsible();
        yield ImageField::new('image','Bild')
            ->setBasePath('/images/kurse/')
            ->setUploadDir('public/images/kurse/')
            ->setUploadedFileNamePattern('[name].[extension]')
            ->setRequired(false)
            ->setColumns(5)
            ->hideOnIndex();
        /*
         * Legacy-Kategorie.
         *
         * Noch nicht entfernen, solange alter Kurs-Code darauf
         * zugreift.
         */
        yield ChoiceField::new('category','Kategorie')
            ->setChoices([
                'Beginner' => 'beginner',
                'Aufbaukurse' => 'aufbau',
                'Sonderkurse' => 'sonder',
                'Mischgas-Kurse' => 'mischgas',
                'Technische Kurse' => 'technisch',
            ])
            ->setColumns(3);
        /*
         * Detailinformationen
         */
        yield FormField::addFieldset('Detailinformation')
            ->collapsible();
        yield TextEditorField::new('requirements','Voraussetzungen')
            ->hideOnIndex()
            ->setColumns(8);
        yield TextEditorField::new('description','Beschreibung')
            ->hideOnIndex()
            ->setColumns(8);
        yield TextareaField::new('requiredEquipment', 'Benötigte Ausrüstung')->hideOnIndex()->setColumns(8);
        /*
         * Notizen
         */
        yield FormField::addFieldset('Notizen')
            ->collapsible();
        yield TextEditorField::new('notes','Bemerkungen')
            ->hideOnIndex()
            ->setColumns(8);

		yield FormField::addFieldset('Ausbildungsplan')
			->collapsible();
		yield CollectionField::new('trainingUnits','Ausbildungseinheiten')
			->setEntryType(
				CourseTrainingUnitType::class
			)
			->allowAdd()
			->allowDelete()
			->setFormTypeOption('by_reference',false)
			->setHelp(
				'Definiert, welche Ausbildungseinheiten dieser Kurs '
				. 'enthält und wie viele Termine jeweils vorgesehen sind.'
			)
			->onlyOnForms();
        /*
         * Teilnehmer
         */
        yield FormField::addFieldset('Teilnehmer')
            ->collapsible();
        yield CollectionField::new('participants','Kursteilnehmer')
            ->setEntryType(
                CourseParticipantType::class
            )
            ->allowAdd()
            ->allowDelete()
            ->setFormTypeOption('by_reference',false)
            ->setHelp('Mitglieder, die an diesem Kurs teilnehmen.')
            ->onlyOnForms();
    }

    public function configureActions(
        Actions $actions
    ): Actions {
        /*
         * Kurs abschließen.
         */
        $completeCourse = Action::new('completeCourse','Kurs abschließen','fa fa-flag-checkered')
            ->linkToRoute('admin_course_complete_confirm', static fn (Courses $course): array => ['id' => $course->getId()])
            ->displayIf(static fn (Courses $course): bool =>$course->getStatus()!== CourseStatus::COMPLETED);
		$generateSchedules = Action::new('generateSchedules','Kurstermine erzeugen','fa fa-calendar-plus')
			->linkToRoute('admin_course_generate_confirm', static fn (Courses $course): array => ['id' => $course->getId()])
			->displayIf(static fn (Courses $course): bool =>!$course->getTrainingUnits()->isEmpty());
        $exportTemplate = Action::new('createTemplateFromCourse','Als Planungsvorlage speichern','fa fa-copy')
            ->linkToCrudAction('createTemplateFromCourse')
            ->displayIf(static fn (Courses $course): bool => $course->getClub() !== null);
        $trainingMatrix = Action::new('trainingMatrix', 'Ausbildungsübersicht', 'fa fa-table')
            ->linkToRoute('admin_course_training_matrix',static fn (Courses $course): array => ['id' => $course->getId()]);
		$planSchedules = Action::new('planSchedules','Termine planen','fa fa-calendar-days')
			->linkToRoute('admin_course_plan',static fn (Courses $course): array => ['id' => $course->getId(),])
			->displayIf(static fn (Courses $course): bool => $course->getStatus() !== CourseStatus::COMPLETED);
        /*
         * Abgeschlossenen Kurs wieder für Korrekturen öffnen.
         */
        $reopenCourse = Action::new('reopenCourse','Kurs wieder öffnen','fa fa-lock-open')
            ->linkToRoute('admin_course_reopen_confirm', static fn (Courses $course): array => ['id' => $course->getId()])
            ->displayIf(static fn (Courses $course): bool =>$course->getStatus()=== CourseStatus::COMPLETED);
        $timetableAction = Action::new('courseTimetable', 'Terminplan', 'fa fa-calendar-check')
            ->linkToRoute('admin_training_timetable', static fn (Courses $course): array => ['id' => $course->getId()]);
		$importTrainingPlan = Action::new('importTrainingPlan','Ausbildungsplan übernehmen','fa fa-list-check')
			->linkToRoute('admin_course_import_confirm', static fn (Courses $course): array => ['id' => $course->getId()])
			->displayIf(static fn (Courses $course): bool =>$course->getQualification() !== null&& $course->getStatus() !== CourseStatus::COMPLETED);
        /*
         * Abgeschlossene Kurse dürfen nicht über die normale
         * Bearbeiten-Aktion geöffnet werden.
         */
        // Die Detailseite als zentrale Arbeitsoberflaeche erreichbar machen.
        $actions->add(Crud::PAGE_INDEX, Action::DETAIL);

        // Auf der Detailseite die haeufigsten Aktionen deutlich hervorheben.
        // Die fachlichen displayIf()-Bedingungen bleiben unveraendert.
        /*$trainingMatrix->addCssClass('btn btn-primary');
        $planSchedules->addCssClass('btn btn-secondary');
        $generateSchedules->addCssClass('btn btn-secondary');
        $importTrainingPlan->addCssClass('btn btn-secondary');
        $exportTemplate->addCssClass('btn btn-secondary');
        $completeCourse->addCssClass('btn btn-warning');
        $reopenCourse->addCssClass('btn btn-secondary');*/

        $actions->add(Crud::PAGE_INDEX, $timetableAction);
        $actions->add(Crud::PAGE_DETAIL, $timetableAction);
        $actions->add(Crud::PAGE_INDEX, $trainingMatrix);
        $actions->add(Crud::PAGE_INDEX, $exportTemplate);
        $actions->add(Crud::PAGE_INDEX, $planSchedules);
        $actions->update(
            Crud::PAGE_INDEX,
            Action::EDIT,
            static fn (Action $action): Action =>
                $action->displayIf(
                    static fn (Courses $course): bool =>
                        $course->getStatus()
                            !== CourseStatus::COMPLETED
                )
        );

        /*
         * Abgeschlossene Kurse nicht löschen.
         */
        $actions->update(
            Crud::PAGE_INDEX,
            Action::DELETE,
            static fn (Action $action): Action =>
                $action->displayIf(
                    static fn (Courses $course): bool =>
                        $course->getStatus()
                            !== CourseStatus::COMPLETED
                )
        );

        return $actions
            ->add(
                Crud::PAGE_INDEX,
                $completeCourse
            )

            ->add(
                Crud::PAGE_INDEX,
                $reopenCourse
            )

			->add(
				Crud::PAGE_INDEX,
				$generateSchedules
			)

			->add(
				Crud::PAGE_INDEX,
				$importTrainingPlan
			)
			->add(
				Crud::PAGE_DETAIL,
				$importTrainingPlan
			);
    }

    /** Loads a course for both EasyAdmin CRUD actions and direct admin URLs. */
    private function resolveActionCourse(
        ?AdminContext $context,
        Request $request,
        EntityManagerInterface $entityManager
    ): Courses {
        if ($context !== null && $context->getCrud() !== null) {
            $entity = $context->getEntity()?->getInstance();
            if ($entity instanceof Courses) {
                $this->assertCourseManage($entity);
                return $entity;
            }
        }

        $rawId = $request->attributes->get('id') ?? $request->query->get('entityId');
        if (!is_scalar($rawId) || !ctype_digit((string) $rawId) || (int) $rawId < 1) {
            throw $this->createNotFoundException('Keine gueltige Kurs-ID uebergeben.');
        }

        $course = $entityManager->getRepository(Courses::class)->find((int) $rawId);
        if (!$course instanceof Courses) {
            throw $this->createNotFoundException('Kurs nicht gefunden.');
        }
        $this->assertCourseManage($course);
        return $course;
    }

    /**
     * Kurs fachlich abschließen.
     */
    public function completeCourse(
        ?AdminContext $context,
        EntityManagerInterface $entityManager,
        Request $request
    ): RedirectResponse {
        $course = $this->resolveActionCourse($context, $request, $entityManager);
        return $this->redirectToRoute('admin_course_complete_confirm', ['id' => $course->getId()]);

        $course = $this->resolveActionCourse($context, $request, $entityManager);

        /*
         * Mehrfachen Abschluss verhindern.
         */
        if (
            $course->getStatus()
            === CourseStatus::COMPLETED
        ) {
            $this->addFlash(
                'info',
                new TranslatableMessage('course.already_completed', ['%course%' => (string) $course])
            );

            return $this->redirectToReferrer(
                $context
            );
        }

        /*
         * Alle Teilnehmer müssen einen echten Endzustand haben.
         */
        if (!$course->canBeCompleted()) {
            $this->addFlash(
                'warning',
                'Der Kurs kann noch nicht abgeschlossen werden. '
                . 'Alle Teilnehmer müssen zuerst den Status '
                . '"Bestanden", "Nicht bestanden" oder '
                . '"Abgebrochen / storniert" besitzen.'
            );

            return $this->redirectToReferrer(
                $context
            );
        }

        $course->setStatus(
            CourseStatus::COMPLETED
        );

        $entityManager->flush();

        $this->addFlash(
            'success',
            new TranslatableMessage('course.completed', ['%course%' => (string) $course])
        );

        return $this->redirectToReferrer(
            $context
        );
    }

    /**
     * Abgeschlossenen Kurs wieder öffnen.
     *
     * Der Kurs wird RUNNING und nicht OPEN, weil die Wiederöffnung
     * für Korrekturen gedacht ist und nicht automatisch eine neue
     * Anmeldephase starten soll.
     */
    public function reopenCourse(
        ?AdminContext $context,
        EntityManagerInterface $entityManager,
        Request $request
    ): RedirectResponse {
        $course = $this->resolveActionCourse($context, $request, $entityManager);
        return $this->redirectToRoute('admin_course_reopen_confirm', ['id' => $course->getId()]);

        $course = $this->resolveActionCourse($context, $request, $entityManager);

        if (
            $course->getStatus()
            !== CourseStatus::COMPLETED
        ) {
            $this->addFlash(
                'info',
                'Der Kurs ist nicht abgeschlossen.'
            );

            return $this->redirectToReferrer(
                $context
            );
        }

        $course->setStatus(
            CourseStatus::RUNNING
        );

        $entityManager->flush();

        $this->addFlash(
            'success',
            new TranslatableMessage('course.reopened', ['%course%' => (string) $course])
        );

        return $this->redirectToReferrer(
            $context
        );
    }

    /** Legacy EasyAdmin action: GET redirects to the confirmation, never mutates. */
    public function importTrainingPlan(
        ?AdminContext $context,
        EntityManagerInterface $entityManager,
        Request $request
    ): RedirectResponse {
        if (!$request->isMethod('GET')) {
            throw $this->createAccessDeniedException('Bitte das geschuetzte Bestaetigungsformular verwenden.');
        }
        $course = $this->resolveActionCourse($context, $request, $entityManager);
        return $this->redirectToRoute('admin_course_import_confirm', ['id' => $course->getId()]);
    }

    /**
     * Serverseitiger Schutz abgeschlossener Kurse.
     */
    public function updateEntity(
        EntityManagerInterface $entityManager,
        $entityInstance
    ): void {
        if (
            $entityInstance instanceof Courses
            && $entityInstance->getStatus()
                === CourseStatus::COMPLETED
        ) {
            throw new \LogicException(
                'Ein abgeschlossener Kurs kann nicht bearbeitet werden. '
                . 'Öffne den Kurs zuerst wieder.'
            );
        }

        if ($entityInstance instanceof Courses) {
            $this->enforceCourseClub($entityInstance, $entityManager);
            $this->assertCourseManage($entityInstance);
            $this->validateLeadInstructor($entityInstance);
        }
        parent::updateEntity(
            $entityManager,
            $entityInstance
        );
    }

    /**
     * Abgeschlossene Kurse nicht löschen.
     */
    public function deleteEntity(
        EntityManagerInterface $entityManager,
        $entityInstance
    ): void {
        if (
            $entityInstance instanceof Courses
            && $entityInstance->getStatus()
                === CourseStatus::COMPLETED
        ) {
            throw new \LogicException(
                'Ein abgeschlossener Kurs kann nicht gelöscht werden. '
                . 'Öffne den Kurs zuerst wieder.'
            );
        }

        if ($entityInstance instanceof Courses) {
            $this->assertCourseManage($entityInstance);
        }
        parent::deleteEntity(
            $entityManager,
            $entityInstance
        );
    }

    /**
     * Zur aufrufenden EasyAdmin-Seite zurück.
     */
    private function redirectToReferrer(
        ?AdminContext $context
    ): RedirectResponse {
        $referrer = $context?->getReferrer();
        if (is_string($referrer) && $referrer !== '') {
            // Avoid redirecting to arbitrary external hosts.
            $parts = parse_url($referrer);
            if ($parts !== false && !isset($parts['host']) && str_starts_with($referrer, '/')) {
                return $this->redirect($referrer);
            }
        }
        return $this->redirectToRoute('admin');
    }

    /** Legacy EasyAdmin action: GET redirects to the confirmation, never mutates. */
    public function generateSchedules(
        ?AdminContext $context,
        Request $request,
        EntityManagerInterface $entityManager
    ): RedirectResponse {
        if (!$request->isMethod('GET')) {
            throw $this->createAccessDeniedException('Bitte das geschuetzte Bestaetigungsformular verwenden.');
        }
        $course = $this->resolveActionCourse($context, $request, $entityManager);
        return $this->redirectToRoute('admin_course_generate_confirm', ['id' => $course->getId()]);
    }

	#[Route(
		'/admin/course/{id}/plan',
		name: 'admin_course_plan',
		requirements: ['id' => '\d+'],
		methods: ['GET', 'POST']
	)]
	public function planSchedules(
		Request $request,
		ScheduleRepository $scheduleRepository,
		EntityManagerInterface $entityManager,
		ScheduleConflictService $conflictService,
		ScheduleTemplateRepository $templateRepository
	): Response {

        if ($request->isMethod('GET') && !$request->attributes->get('ea_workflow_embedded')) {
            return $this->redirectToRoute('admin_training_workflow', [
                'id' => $request->attributes->get('id'), 'step' => 'plan',
            ], Response::HTTP_SEE_OTHER);
        }

		/* Kurs-ID aus der eigenstaendigen GET/POST-Route ermitteln. */
		$courseId = $request->attributes->get('id');

		if (
			!is_scalar($courseId)
			|| !ctype_digit((string) $courseId)
			|| (int) $courseId < 1
		) {
			throw $this->createNotFoundException(
				'Keine gültige Kurs-ID übergeben.'
			);
		}

		$course = $entityManager
			->getRepository(Courses::class)
			->find((int) $courseId);

		if (!$course instanceof Courses) {
			throw $this->createNotFoundException(
				'Der angeforderte Kurs wurde nicht gefunden.'
			);
		}

        if ($course->getStatus() === CourseStatus::COMPLETED) {
            throw $this->createAccessDeniedException(
                'Abgeschlossene Kurse können nicht geplant werden.'
            );
        }

        $schedules = $scheduleRepository->findBy(
            ['courses' => $course],
            ['trainingUnitSequence' => 'ASC', 'id' => 'ASC']
        );

        // In der Planungsmaske fachlich gruppieren, unabhaengig von
        // Erzeugungsreihenfolge und Datenbank-ID.
        // Innerhalb jeder Gruppe die laufende Nummer beibehalten.
        $trainingPhase = static function (\App\Entity\Schedule $schedule): int {
            $type = $schedule->getTrainingUnitType();
            $text = mb_strtolower(trim(
                ($type?->getCode() ?? '') . ' ' . ($type?->getName() ?? '') . ' ' . ($schedule->getTitle() ?? '')
            ), 'UTF-8');
            if (preg_match('/theorie|theory|pruefung|prüfung|exam/u', $text)) return 0;
            if (preg_match('/schwimmbad|pool|hallenbad|becken/u', $text)) return 1;
            if (preg_match('/freiwasser|open.?water|see|tauchgang/u', $text)) return 2;
            return 3;
        };
        usort($schedules, static function ($a, $b) use ($trainingPhase): int {
            $phase = $trainingPhase($a) <=> $trainingPhase($b);
            if ($phase !== 0) return $phase;
            $sequence = ($a->getTrainingUnitSequence() ?? PHP_INT_MAX)
                <=> ($b->getTrainingUnitSequence() ?? PHP_INT_MAX);
            if ($sequence !== 0) return $sequence;
            return ($a->getId() ?? 0) <=> ($b->getId() ?? 0);
        });

        if ($schedules === []) {
            $this->addFlash('warning', 'Bitte zuerst Kurstermine erzeugen.');
            return $this->redirectToRoute('admin');
        }

        foreach ($schedules as $schedule) {
            if ($schedule->getInstructor() === null && $course->getLeadInstructor() !== null) {
                $schedule->setInstructor($course->getLeadInstructor());
            }
        }

		$form = $this->createForm(
			CourseSchedulePlanningType::class,
			['schedules' => $schedules],
			[
                'club' => $course->getClub(),
				'action' => $this->generateUrl(
					'admin_course_plan',
					['id' => $course->getId()]
				),
				'method' => 'POST',
			]
		);
		
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Die Formulardaten befinden sich bereits in den verwalteten
            // Schedule-Objekten; die Prüfung erfolgt vor dem flush().
            $conflicts = $conflictService->findConflicts($schedules);
            $messages = [];

            foreach ($conflicts as $conflict) {
                $first = $conflict['first'];
                $second = $conflict['second'];

                $parameters = [
                    '%first%' => $first->getTitle() ?? '',
                    '%second%' => $second->getTitle() ?? '',
                    '%instructor%' => $first->getInstructor()?->getFullName() ?? '',
                ];
                $messages[json_encode($parameters)] = new TranslatableMessage('planning.server_conflict', $parameters);
            }

            if ($messages !== []) {
                foreach ($messages as $message) $this->addFlash('danger', $message);
                $this->addFlash('danger', 'Terminüberschneidung: Planung nicht gespeichert.');
                return $this->render('admin/course/schedule_planning.html.twig', [
                    'course' => $course, 'form' => $form->createView(), 'templatePlans' => [],
                ]);
            }
            $entityManager->flush();

            foreach ($messages as $message) {
                $this->addFlash('warning', $message);
            }

            $this->addFlash('success', 'Die Terminplanung wurde gespeichert.');
            return $this->redirect($request->getUri());
        }

        // Nur Vorlagen des Kursvereins zur Auswahl anbieten.
        $templatePlans = [];
        if ($course->getClub() !== null) {
            $templates = $templateRepository->findBy(
                ['club' => $course->getClub()],
                ['name' => 'ASC']
            );
            foreach ($templates as $template) {
                $entries = [];
                foreach ($template->getEntries() as $entry) {
                    $type = $entry->getTrainingUnitType();
                    if ($type === null || $type->getId() === null) {
                        continue;
                    }
                    $entries[] = [
                        'typeId' => $type->getId(),
                        'sequence' => $entry->getSequence(),
                        'dayOffset' => $entry->getDayOffset(),
                        'time' => $entry->getStartTime()?->format('H:i'),
                        'minutes' => $entry->getDurationMinutes(),
                    ];
                }
                $templatePlans[] = [
                    'id' => $template->getId(),
                    'name' => $template->getName(),
                    'entries' => $entries,
                ];
            }
        }

        return $this->render('admin/course/schedule_planning.html.twig', [
            'course' => $course,
            'form' => $form->createView(),
            'templatePlans' => $templatePlans,
        ]);
    }

    /**
     * Erstellt aus bereits geplanten Kursterminen eine wiederverwendbare
     * Vereinsvorlage. GET zeigt Vorschau, POST erstellt nach CSRF-Pruefung.
     */
    public function createTemplateFromCourse(
        ?AdminContext $context,
        Request $request,
        ScheduleRepository $scheduleRepository,
        CourseTemplateExportService $exportService,
        EntityManagerInterface $entityManager
    ): Response {
        $course = $this->resolveActionCourse($context, $request, $entityManager);
        $club = $course->getClub();
        if ($club === null) {
            throw new \LogicException('Der Kurs ist keinem Verein zugeordnet.');
        }

        $schedules = $scheduleRepository->findBy(
            ['courses' => $course],
            ['startDate' => 'ASC', 'startTime' => 'ASC', 'id' => 'ASC']
        );
        $export = $exportService->prepare($schedules, $club);
        $defaultName = $this->translator->trans('template.default_name', ['%course%' => (string) $course]);
        $name = $defaultName;
        $description = '';
        $error = null;

        if ($request->isMethod('POST')) {
            $name = trim((string) $request->request->get('name', ''));
            $description = trim((string) $request->request->get('description', ''));
            $token = (string) $request->request->get('_token', '');

            if (!$this->isCsrfTokenValid('course_template_export_' . $course->getId(), $token)) {
                throw $this->createAccessDeniedException('Ungueltiger CSRF-Token.');
            }
            if (mb_strlen($name) < 3 || mb_strlen($name) > 150) {
                $error = 'Der Vorlagenname muss 3 bis 150 Zeichen enthalten.';
            } elseif ($export['entries'] === []) {
                $error = 'Keine geeigneten geplanten Kurstermine vorhanden.';
            } else {
                $template = new ScheduleTemplate();
                $template->setClub($club);
                $template->setName($name);
                $template->setDescription($description !== '' ? $description : null);

                foreach ($export['entries'] as $data) {
                    $entry = new ScheduleTemplateEntry();
                    $entry->setTrainingUnitType($data['type']);
                    $entry->setSequence($data['sequence']);
                    $entry->setDayOffset($data['dayOffset']);
                    $entry->setStartTime($data['startTime']);
                    $entry->setDurationMinutes($data['durationMinutes']);
                    $template->addEntry($entry);
                }

                $entityManager->persist($template);
                $entityManager->flush();
                $this->addFlash('success', new TranslatableMessage('template.saved', [
                    '%name%' => $name, '%count%' => count($export['entries']),
                ]));
                return $this->redirectToReferrer($context);
            }
        }

        return $this->render('admin/course/export_schedule_template.html.twig', [
            'course' => $course,
            'entries' => $export['entries'],
            'skipped' => $export['skipped'],
            'baseDate' => $export['baseDate'],
            'name' => $name,
            'description' => $description,
            'error' => $error,
        ]);
    }

}
