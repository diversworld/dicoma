<?php

namespace App\Controller\Admin;

use App\Entity\Schedule;
use App\Enum\CourseStatus;
use App\Enum\CourseParticipantStatus;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Translation\TranslatableMessage;
use App\Service\ScheduleParticipantSyncService;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TimeField;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Form\ScheduleAttendanceType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use App\Enum\BookingAttendanceStatus;

#[IsGranted('ROLE_SUPER_ADMIN')]
class ScheduleCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly ScheduleParticipantSyncService $participantSyncService
    ) {
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        parent::persistEntity($entityManager, $entityInstance);

        if ($entityInstance instanceof Schedule && $entityInstance->getCourses() !== null) {
            $created = $this->participantSyncService->sync($entityInstance);
            if ($created > 0) {
                $this->addFlash('success', sprintf('%d Terminbuchung(en) automatisch angelegt.', $created));
            }
        }
    }

    public static function getEntityFqcn(): string
    {
        return Schedule::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Kurstermin')
            ->setEntityLabelInPlural('Kurstermine')
            ->setPageTitle(
                Crud::PAGE_INDEX,
                'Kurstermine'
            )
            ->setPageTitle(
                Crud::PAGE_NEW,
                'Kurstermin erstellen'
            )
            ->setPageTitle(
                Crud::PAGE_EDIT,
                'Kurstermin bearbeiten'
            )
            ->setPageTitle(
                Crud::PAGE_DETAIL,
                'Kurstermin'
            )
            ->setDefaultSort([
                'startDate' => 'DESC',
                'startTime' => 'ASC',
            ])
            ->setSearchFields([
                'title',
                'location',
                'locationCity',
                'courses.title',
                'instructor.person.firstname',
                'instructor.person.lastname',
            ]);
    }

    public function configureFields(string $pageName): iterable
    {
        /*
         * Termin
         */
        yield FormField::addFieldset('Kurstermininformation')
            ->collapsible();

        yield IdField::new('id','ID')
            ->setColumns(1)
            ->hideOnForm();

        yield TextField::new('title','Terminbezeichnung')
            ->setColumns(6);

        yield ImageField::new('image','Bild')
            ->setBasePath('/images/kurse/')
            ->setUploadDir('public/images/kurse/')
            ->setUploadedFileNamePattern('[randomness].[extension]')
            ->setRequired(false)
            ->setColumns(5)
            ->hideOnIndex();

        /*
         * Datum / Uhrzeit
         */
        yield FormField::addFieldset('Termininformationen')
            ->collapsible();

        yield DateField::new('startDate','Beginnt am')
            ->setColumns(2)
            ->setFormat('dd.MM.yyyy');

        yield TimeField::new('startTime','Uhrzeit')
            ->setColumns(2)
            ->setFormat('HH:mm');
		yield TextField::new('timeRange','Zeitraum')
			->onlyOnIndex();

		yield TextField::new('endTimeLabel','Ende')
			->onlyOnDetail();
		
		yield TextField::new('attendanceSummary','Anwesenheit')
			->onlyOnIndex();
		
        yield NumberField::new('durationHours','Dauer (Stunden)')
			->setNumDecimals(2)
			->setColumns(2)
			->onlyOnForms();
		yield TextField::new('durationLabel','Dauer')
			->onlyOnIndex();
		
		yield AssociationField::new('trainingUnitType','Ausbildungseinheit')
			->setColumns(4)
			->setHelp('Art bzw. Ausbildungsinhalt dieses Termins, z. B. Theorie, Schwimmbad, ABC-Praxis oder Prüfung.');
		
		yield AssociationField::new('trainingUnitType','Ausbildungseinheit');
		
        /*
         * Kurs
         */
        yield FormField::addFieldset('Kursinformation')
            ->collapsible();

		yield AssociationField::new('courses','Kurs')
            ->setRequired(true)
            ->setColumns(5);

        yield MoneyField::new('price','Preis')
            ->setColumns(2)
            ->setCurrency('EUR');

        /*
         * Den alten category=instructor-Filter verwenden
         * wir nicht mehr.
         *
         * Die Auswahl von Ausbildern wird später über
         * Qualifikationen/Rollen eingeschränkt.
         */
        yield AssociationField::new('instructor','Instruktor')
            ->setColumns(4);

        /*
         * Ort
         */
        yield FormField::addFieldset('Adressinformationen')
            ->collapsible();

        yield TextField::new('location','Veranstaltungsort')
            ->setColumns(5);

        yield TextField::new('locationStreet','Straße')
            ->setColumns(5)
            ->hideOnIndex();

        /*
         * PLZ ist fachlich kein Integer.
         *
         * Deutsche PLZ können mit 0 beginnen.
         * Wenn Schedule.locationPostal derzeit noch int ist,
         * sollten wir das im nächsten Schritt auf string ändern.
         */
        yield IntegerField::new('locationPostal','PLZ')
            ->setColumns(2)
            ->hideOnIndex();

        yield TextField::new('locationCity','Ort')
            ->setColumns(4)
            ->hideOnIndex();

        /*
         * Bemerkungen
         */
        yield FormField::addFieldset('Bemerkungen')
            ->collapsible();

        yield TextEditorField::new('notes','Notizen')
            ->setColumns(8)
            ->hideOnIndex();

        /*
         * Buchungen zeigen wir in der Übersicht/Detailansicht
         * an, bearbeiten sie aber nicht als Collection innerhalb
         * des Terminformulars.
         */
        yield AssociationField::new('bookings','Terminbuchungen')
            ->onlyOnDetail();
    }

    public function configureActions(
		Actions $actions
	): Actions {
		$syncParticipants = Action::new(
			'syncParticipants',
			'Teilnehmer übernehmen',
			'fa fa-users'
		)
			->linkToRoute('admin_schedule_sync_participants', static fn (Schedule $schedule): array => ['id' => $schedule->getId()]);

		$attendance = Action::new(
			'attendance',
			'Anwesenheit erfassen',
			'fa fa-user-check'
		)
			->linkToRoute('admin_schedule_attendance', static fn (Schedule $schedule): array => ['id' => $schedule->getId()])
			->displayIf(
				static fn (Schedule $schedule): bool =>
					!$schedule->getBookings()->isEmpty()
			);

		return $actions
			->add(
				Crud::PAGE_INDEX,
				$syncParticipants
			)
			->add(
				Crud::PAGE_DETAIL,
				$syncParticipants
			)
			->add(
				Crud::PAGE_INDEX,
				$attendance
			)
			->add(
				Crud::PAGE_DETAIL,
				$attendance
			);
	}

	#[Route('/admin/schedule/{id}/attendance', name: 'admin_schedule_attendance', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
	public function attendance(
		Request $request,
		EntityManagerInterface $entityManager
	): Response {
        $schedule = $this->resolveSchedule($request, $entityManager);

		if ($schedule->getBookings()->isEmpty()) {
			$this->addFlash(
				'warning',
				'Für diesen Kurstermin sind noch keine Teilnehmer '
				. 'eingetragen. Verwende zuerst '
				. '"Teilnehmer übernehmen".'
			);

			return $this->redirectToRoute('admin_schedule_sync_participants', ['id' => $schedule->getId()]);
		}

		$data = [
			'bookings' => $schedule->getBookings(),
		];

		$form = $this->createForm(
			ScheduleAttendanceType::class,
			$data
		);

		$form->handleRequest($request);

		/*
		 * Alle Teilnehmer mit einem Klick als anwesend markieren.
		 *
		 * Wir speichern hier bewusst noch nicht.
		 * Der Benutzer kann anschließend einzelne Teilnehmer
		 * beispielsweise auf "Entschuldigt" ändern.
		 */
		if (
			$form->isSubmitted()
			&& $form->get('markAllPresent')->isClicked()
            && $form->isValid()
		) {
			foreach ($schedule->getBookings() as $booking) {
				$booking->setAttendanceStatus(
					BookingAttendanceStatus::PRESENT
				);
			}

			/*
			 * Neues Formular erzeugen, damit die geänderten
			 * Entity-Werte sofort angezeigt werden.
			 */
			$data = [
				'bookings' => $schedule->getBookings(),
			];

			$form = $this->createForm(
				ScheduleAttendanceType::class,
				$data
			);

			$this->addFlash(
				'info',
				'Alle Teilnehmer wurden als anwesend markiert. '
				. 'Prüfe gegebenenfalls Abweichungen und '
				. 'speichere anschließend die Anwesenheit.'
			);
		} elseif (
			$form->isSubmitted()
			&& $form->get('save')->isClicked()
			&& $form->isValid()
		) {
			$entityManager->flush();

			$this->addFlash(
				'success',
				new TranslatableMessage('attendance.saved', ['%schedule%' => $schedule->getTitle()])
			);

			return $this->redirectToSchedule($schedule);
		}

		return $this->render(
			'admin/schedule/attendance.html.twig',
			[
				'schedule' => $schedule,
				'form' => $form->createView(),
			]
		);
	}
	
    /** GET only previews; POST with CSRF performs the import. */
    #[Route('/admin/schedule/{id}/sync-participants', name: 'admin_schedule_sync_participants', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function syncParticipants(Request $request, EntityManagerInterface $entityManager): Response
    {
        $schedule = $this->resolveSchedule($request, $entityManager);
        $course = $schedule->getCourses();
        if ($course === null) {
            throw $this->createNotFoundException('Der Kurstermin hat keinen Kurs.');
        }
        if ($course->getStatus() === CourseStatus::COMPLETED) {
            throw $this->createAccessDeniedException('Abgeschlossene Kurse können nicht synchronisiert werden.');
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('sync_schedule_' . $schedule->getId(), (string) $request->request->get('_token', ''))) {
                throw $this->createAccessDeniedException('Ungültiger CSRF-Token.');
            }
            try {
                $created = $this->participantSyncService->sync($schedule);
                $this->addFlash($created ? 'success' : 'info', $created
                    ? sprintf('%d Terminbuchung(en) angelegt.', $created)
                    : 'Keine neuen Buchungen notwendig.');
            } catch (\LogicException $exception) {
                $this->addFlash('danger', $exception->getMessage());
            }
            return $this->redirectToSchedule($schedule);
        }

        $bookedMemberIds = [];
        $bookedParticipantIds = [];
        foreach ($schedule->getBookings() as $booking) {
            if ($booking->getCourseParticipant()?->getId() !== null) {
                $bookedParticipantIds[$booking->getCourseParticipant()->getId()] = true;
            }
            $memberId = $booking->getMember()?->getId();
            if ($memberId !== null) {
                $bookedMemberIds[$memberId] = true;
            }
        }

        $preview = [
            'total' => $course->getParticipants()->count(),
            'eligible' => 0,
            'existing' => 0,
            'missing' => 0,
            'excluded' => 0,
        ];
        $missingParticipants = [];
        foreach ($course->getParticipants() as $participant) {
            $member = $participant->getMember();
            if (!in_array($participant->getStatus(), [CourseParticipantStatus::REGISTERED, CourseParticipantStatus::ACTIVE], true)) {
                ++$preview['excluded'];
                continue;
            }

            ++$preview['eligible'];
            if (isset($bookedParticipantIds[$participant->getId()])
                || ($member?->getId() !== null && isset($bookedMemberIds[$member->getId()]))) {
                ++$preview['existing'];
                continue;
            }

            ++$preview['missing'];
            $missingParticipants[] = $participant->getFullName();
        }

        natcasesort($missingParticipants);

        return $this->render('admin/schedule/confirm_sync.html.twig', [
            'schedule' => $schedule,
            'course' => $course,
            'preview' => $preview,
            'missingParticipants' => array_values($missingParticipants),
        ]);
    }

    private function resolveSchedule(Request $request, EntityManagerInterface $em): Schedule
    {
        $raw = $request->attributes->get('id') ?? $request->query->get('entityId');
        if (!is_scalar($raw) || !ctype_digit((string) $raw) || (int) $raw < 1) {
            throw $this->createNotFoundException('Ungültige Kurstermin-ID.');
        }
        $schedule = $em->getRepository(Schedule::class)->find((int) $raw);
        if (!$schedule instanceof Schedule) {
            throw $this->createNotFoundException('Kurstermin nicht gefunden.');
        }
        if ($schedule->getCourses() !== null) {
            $this->denyAccessUnlessGranted(\App\Security\Voter\CourseManagementVoter::MANAGE, $schedule->getCourses());
            if ($schedule->getCourses()->getStatus() === CourseStatus::COMPLETED) {
                throw $this->createAccessDeniedException('Abgeschlossene Kurse duerfen nicht veraendert werden.');
            }
        }
        return $schedule;
    }

    private function redirectToSchedule(Schedule $schedule): RedirectResponse
    {
        return $this->redirectToRoute('admin_schedule_attendance', ['id' => $schedule->getId()]);
    }
}
