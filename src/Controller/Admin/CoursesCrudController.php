<?php

namespace App\Controller\Admin;

use App\Entity\Courses;
<<<<<<< HEAD
use App\Entity\ScheduleTemplate;
use App\Entity\ScheduleTemplateEntry;
use App\Enum\CourseStatus;
use App\Form\CourseParticipantType;
use App\Form\CourseTrainingUnitType;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
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
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Service\CourseScheduleGenerator;
use App\Service\CourseTemplateExportService;
use App\Service\CourseTrainingPlanService;
use App\Service\ScheduleConflictService;
use App\Repository\ScheduleTemplateRepository;
use App\Form\CourseSchedulePlanningType;
use App\Repository\ScheduleRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[IsGranted('ROLE_ADMIN')]
class CoursesCrudController extends AbstractCrudController
{
	public function __construct(
		private readonly CourseScheduleGenerator $scheduleGenerator,
		private readonly CourseTrainingPlanService $trainingPlanService
	) {
	}
	
=======
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class CoursesCrudController extends AbstractCrudController
{
>>>>>>> origin/main
    public static function getEntityFqcn(): string
    {
        return Courses::class;
    }

<<<<<<< HEAD
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
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

        yield AssociationField::new(
            'club',
            'Verein'
        )
            ->setRequired(true)
            ->setColumns(4);

        yield TextField::new(
            'title',
            'Titel'
        )
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

            yield ChoiceField::new(
                'status',
                'Kursstatus'
            )
                ->setChoices($statusChoices)
                ->setColumns(3)
                ->setFormTypeOption(
                    'disabled',
                    $isCompleted
                );
        }

        yield AssociationField::new(
            'qualification',
            'Zielqualifikation'
        )
            ->setRequired(false)
            ->setColumns(4)
            ->setHelp(
                'Qualifikation oder Brevet, das nach erfolgreichem '
                . 'Abschluss des Kurses erworben wird.'
            );

        /*
         * Kapazität / Anmeldung
         */
        yield IntegerField::new(
            'participantCount',
            'Teilnehmer'
        )
            ->onlyOnIndex();

        yield IntegerField::new(
            'freePlaces',
            'Freie Plätze'
        )
            ->onlyOnIndex();

        yield IntegerField::new(
            'maxParticipants',
            'Max. Teilnehmer'
        )
            ->setColumns(3)
            ->setHelp(
                'Leer lassen, wenn die Teilnehmerzahl '
                . 'nicht begrenzt ist.'
            );

        yield DateField::new(
            'registrationDeadline',
            'Anmeldeschluss'
        )
            ->setFormat('dd.MM.yyyy')
            ->setColumns(3);

        /*
         * Bild
         */
        yield FormField::addFieldset('Bild')
            ->collapsible();

        yield ImageField::new(
            'image',
            'Bild'
        )
            ->setBasePath('/images/kurse/')
            ->setUploadDir('public/images/kurse/')
            ->setUploadedFileNamePattern(
                '[name].[extension]'
            )
            ->setRequired(false)
            ->setColumns(5)
            ->hideOnIndex();

        /*
         * Legacy-Kategorie.
         *
         * Noch nicht entfernen, solange alter Kurs-Code darauf
         * zugreift.
         */
        yield ChoiceField::new(
            'category',
            'Kategorie'
        )
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

        yield TextEditorField::new(
            'requirements',
            'Voraussetzungen'
        )
            ->hideOnIndex()
            ->setColumns(8);

        yield TextEditorField::new(
            'description',
            'Beschreibung'
        )
            ->hideOnIndex()
            ->setColumns(8);

        /*
         * Notizen
         */
        yield FormField::addFieldset('Notizen')
            ->collapsible();

        yield TextEditorField::new(
            'notes',
            'Bemerkungen'
        )
            ->hideOnIndex()
            ->setColumns(8);

		yield FormField::addFieldset(
			'Ausbildungsplan'
		)
			->collapsible();

		yield CollectionField::new(
			'trainingUnits',
			'Ausbildungseinheiten'
		)
			->setEntryType(
				CourseTrainingUnitType::class
			)
			->allowAdd()
			->allowDelete()
			->setFormTypeOption(
				'by_reference',
				false
			)
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

        yield CollectionField::new(
            'participants',
            'Kursteilnehmer'
        )
            ->setEntryType(
                CourseParticipantType::class
            )
            ->allowAdd()
            ->allowDelete()
            ->setFormTypeOption(
                'by_reference',
                false
            )
            ->setHelp(
                'Mitglieder, die an diesem Kurs teilnehmen.'
            )
            ->onlyOnForms();
    }

    public function configureActions(
        Actions $actions
    ): Actions {
        /*
         * Kurs abschließen.
         */
        $completeCourse = Action::new(
            'completeCourse',
            'Kurs abschließen',
            'fa fa-flag-checkered'
        )
            ->linkToCrudAction(
                'completeCourse'
            )
            ->displayIf(
                static fn (Courses $course): bool =>
                    $course->getStatus()
                        !== CourseStatus::COMPLETED
            );
		
		$generateSchedules = Action::new(
			'generateSchedules',
			'Kurstermine erzeugen',
			'fa fa-calendar-plus'
		)
			->linkToCrudAction(
			'generateSchedules'
		)
			->displayIf(
			static fn (Courses $course): bool =>
			!$course->getTrainingUnits()->isEmpty()
		);
        $exportTemplate = Action::new(
            'createTemplateFromCourse',
            'Als Planungsvorlage speichern',
            'fa fa-copy'
        )
            ->linkToCrudAction('createTemplateFromCourse')
            ->displayIf(static fn (Courses $course): bool => $course->getClub() !== null);

        $planSchedules = Action::new('planSchedules', 'Termine planen', 'fa fa-calendar-days')
            ->linkToCrudAction('planSchedules')
            ->displayIf(static fn (Courses $course): bool => $course->getStatus() !== CourseStatus::COMPLETED);

        /*
         * Abgeschlossenen Kurs wieder für Korrekturen öffnen.
         */
        $reopenCourse = Action::new(
            'reopenCourse',
            'Kurs wieder öffnen',
            'fa fa-lock-open'
        )
            ->linkToCrudAction(
                'reopenCourse'
            )
            ->displayIf(
                static fn (Courses $course): bool =>
                    $course->getStatus()
                        === CourseStatus::COMPLETED
            );

		$importTrainingPlan = Action::new(
			'importTrainingPlan',
			'Ausbildungsplan übernehmen',
			'fa fa-list-check'
		)
			->linkToCrudAction(
				'importTrainingPlan'
			)
			->displayIf(
				static fn (Courses $course): bool =>
					$course->getQualification() !== null
					&& $course->getStatus() !== CourseStatus::COMPLETED
			);
        /*
         * Abgeschlossene Kurse dürfen nicht über die normale
         * Bearbeiten-Aktion geöffnet werden.
         */
        $actions->add(Crud::PAGE_INDEX, $exportTemplate);
        $actions->add(Crud::PAGE_DETAIL, $exportTemplate);
        $actions->add(Crud::PAGE_INDEX, $planSchedules);
        $actions->add(Crud::PAGE_DETAIL, $planSchedules);

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
                Crud::PAGE_DETAIL,
                $completeCourse
            )
            ->add(
                Crud::PAGE_INDEX,
                $reopenCourse
            )
            ->add(
                Crud::PAGE_DETAIL,
                $reopenCourse
            )
			->add(
				Crud::PAGE_INDEX,
				$generateSchedules
			)
			->add(
				Crud::PAGE_DETAIL,
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

    /**
     * Kurs fachlich abschließen.
     */
    public function completeCourse(
        AdminContext $context,
        EntityManagerInterface $entityManager
    ): RedirectResponse {
        $course = $context
            ->getEntity()
            ->getInstance();

        if (!$course instanceof Courses) {
            throw new \LogicException(
                'Ungültiger Kurs.'
            );
        }

        /*
         * Mehrfachen Abschluss verhindern.
         */
        if (
            $course->getStatus()
            === CourseStatus::COMPLETED
        ) {
            $this->addFlash(
                'info',
                sprintf(
                    'Der Kurs "%s" ist bereits abgeschlossen.',
                    (string) $course
                )
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
            sprintf(
                'Der Kurs "%s" wurde erfolgreich abgeschlossen.',
                (string) $course
            )
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
        AdminContext $context,
        EntityManagerInterface $entityManager
    ): RedirectResponse {
        $course = $context
            ->getEntity()
            ->getInstance();

        if (!$course instanceof Courses) {
            throw new \LogicException(
                'Ungültiger Kurs.'
            );
        }

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
            sprintf(
                'Der Kurs "%s" wurde wieder geöffnet.',
                (string) $course
            )
        );

        return $this->redirectToReferrer(
            $context
        );
    }

	public function importTrainingPlan(
		AdminContext $context,
		EntityManagerInterface $entityManager
	): RedirectResponse {
		$course = $context
			->getEntity()
			->getInstance();

		if (!$course instanceof Courses) {
			throw new \LogicException(
				'Ungültiger Kurs.'
			);
		}

		try {
			$created = $this
				->trainingPlanService
				->importFromQualification($course);

			if ($created === 0) {
				$this->addFlash(
					'info',
					'Der Ausbildungsplan der Zielqualifikation '
					. 'ist bereits vollständig im Kurs vorhanden.'
				);
			} else {
				$entityManager->flush();

				$this->addFlash(
					'success',
					sprintf(
						'%d %s aus der Zielqualifikation übernommen.',
						$created,
						$created === 1
							? 'Ausbildungseinheit wurde'
							: 'Ausbildungseinheiten wurden'
					)
				);
			}
		} catch (\LogicException $exception) {
			$this->addFlash(
				'danger',
				$exception->getMessage()
			);
		}

		return $this->redirect(
			$context->getReferrer()
			?: $this->generateUrl('admin')
		);
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

        parent::deleteEntity(
            $entityManager,
            $entityInstance
        );
    }

    /**
     * Zur aufrufenden EasyAdmin-Seite zurück.
     */
    private function redirectToReferrer(
        AdminContext $context
    ): RedirectResponse {
        $referrer = $context->getReferrer();

        if (
            $referrer !== null
            && $referrer !== ''
        ) {
            return $this->redirect(
                $referrer
            );
        }

        return $this->redirectToRoute(
            'admin'
        );
    }

	public function generateSchedules(
		AdminContext $context
	): RedirectResponse {
		$course = $context
			->getEntity()
			->getInstance();

		if (!$course instanceof Courses) {
			throw new \LogicException(
				'Ungültiger Kurs.'
			);
		}

		try {
			$created = $this
				->scheduleGenerator
				->generate($course);

			if ($created === 0) {
				$this->addFlash(
					'info',
					'Für den Ausbildungsplan sind bereits '
					. 'alle benötigten Kurstermine vorhanden.'
				);
			} else {
				$this->addFlash(
					'success',
					sprintf(
						'%d %s erfolgreich erzeugt.',
						$created,
						$created === 1
							? 'Kurstermin wurde'
							: 'Kurstermine wurden'
					)
				);
			}
		} catch (\LogicException $exception) {
			$this->addFlash(
				'danger',
				$exception->getMessage()
			);
		}

		return $this->redirect(
			$context->getReferrer()
			?: $this->generateUrl('admin')
		);
	}
    public function planSchedules(
        AdminContext $context,
        Request $request,
        ScheduleRepository $scheduleRepository,
        EntityManagerInterface $entityManager,
        ScheduleConflictService $conflictService,
        ScheduleTemplateRepository $templateRepository
    ): Response {
        $course = $context->getEntity()->getInstance();

        if (!$course instanceof Courses) {
            throw new \LogicException('Ungültiger Kurs.');
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

        if ($schedules === []) {
            $this->addFlash('warning', 'Bitte zuerst Kurstermine erzeugen.');
            return $this->redirectToReferrer($context);
        }

        $form = $this->createForm(
            CourseSchedulePlanningType::class,
            ['schedules' => $schedules]
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

                $message = sprintf(
                    'Terminkonflikt: "%s" und "%s" überschneiden sich. '
                    . 'Instruktor: %s.',
                    $first->getTitle() ?? '',
                    $second->getTitle() ?? '',
                    $first->getInstructor()?->getFullName() ?? 'Unbekannt'
                );
                $messages[$message] = true;
            }

            $entityManager->flush();

            foreach (array_keys($messages) as $message) {
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
        AdminContext $context,
        Request $request,
        ScheduleRepository $scheduleRepository,
        CourseTemplateExportService $exportService,
        EntityManagerInterface $entityManager
    ): Response {
        $course = $context->getEntity()->getInstance();
        if (!$course instanceof Courses) {
            throw new \LogicException('Ungueltiger Kurs.');
        }
        $club = $course->getClub();
        if ($club === null) {
            throw new \LogicException('Der Kurs ist keinem Verein zugeordnet.');
        }

        $schedules = $scheduleRepository->findBy(
            ['courses' => $course],
            ['startDate' => 'ASC', 'startTime' => 'ASC', 'id' => 'ASC']
        );
        $export = $exportService->prepare($schedules, $club);
        $defaultName = 'Vorlage: ' . (string) $course;
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
                $this->addFlash('success', sprintf(
                    'Planungsvorlage "%s" mit %d Eintraegen gespeichert.',
                    $name,
                    count($export['entries'])
                ));
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
=======
    public function configureFields(string $pageName): iterable
    {
        return [
            FormField::addFieldset('Kursinformation')
                ->collapsible(),
            IdField::new('id')
                ->hideOnForm()
                ->setColumns(3),
            TextField::new('title')
                ->setColumns(5),
            FormField::addFieldset('Bild')
                ->collapsible(),
            ImageField::new('image')
                ->setBasePath('/images/kurse/')
                ->setUploadDir('public/images/kurse/')
                ->setUploadedFileNamePattern('[name].[extension]')
                ->setRequired(false)
                ->setColumns(5),
            ChoiceField::new('category')
                ->setChoices([
                    'Beginner' => 'beginner',
                    'Aufbaukure' => 'aufbau',
                    'Sonderkurse' => 'sonder',
                    'Mischgas Kurse' => 'mischgas',
                    'Technische Kurse' => 'technisch'
                ])
                ->setColumns(2),
            FormField::addFieldset('Detailinformation')
                ->collapsible(),
            TextEditorField::new('requirements')
                ->hideOnIndex()
                ->setColumns(8),
            TextEditorField::new('description')
                ->hideOnIndex()
                ->setColumns(8),
            FormField::addFieldset('Notizen')
                ->collapsible(),
            TextEditorField::new('notes')
                ->hideOnIndex()
                ->setColumns(8),
        ];
>>>>>>> origin/main
    }

}
