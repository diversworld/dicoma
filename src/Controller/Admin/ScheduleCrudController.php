<?php

namespace App\Controller\Admin;

use App\Entity\Schedule;
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

#[IsGranted('ROLE_ADMIN')]
class ScheduleCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly ScheduleParticipantSyncService $participantSyncService
    ) {
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
                'instructor.firstname',
                'instructor.lastname',
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
			->linkToCrudAction(
				'syncParticipants'
			);

		$attendance = Action::new(
			'attendance',
			'Anwesenheit erfassen',
			'fa fa-user-check'
		)
			->linkToCrudAction(
				'attendance'
			)
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

	public function attendance(
		AdminContext $context,
		Request $request,
		EntityManagerInterface $entityManager
	): Response {
		$schedule = $context
			->getEntity()
			->getInstance();

		if (!$schedule instanceof Schedule) {
			throw new \LogicException(
				'Ungültiger Kurstermin.'
			);
		}

		if ($schedule->getBookings()->isEmpty()) {
			$this->addFlash(
				'warning',
				'Für diesen Kurstermin sind noch keine Teilnehmer '
				. 'eingetragen. Verwende zuerst '
				. '"Teilnehmer übernehmen".'
			);

			return $this->redirectToReferrer(
				$context
			);
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
				sprintf(
					'Die Anwesenheit für "%s" wurde gespeichert.',
					$schedule->getTitle()
				)
			);

			return $this->redirectToReferrer(
				$context
			);
		}

		return $this->render(
			'admin/schedule/attendance.html.twig',
			[
				'schedule' => $schedule,
				'form' => $form->createView(),
			]
		);
	}
	
    /**
     * Übernimmt die Teilnehmer des zugeordneten Kurses
     * als Terminbuchungen.
     */
    public function syncParticipants(
        AdminContext $context
    ): RedirectResponse {
        $schedule = $context
            ->getEntity()
            ->getInstance();

        if (!$schedule instanceof Schedule) {
            throw new \LogicException(
                'Ungültiger Kurstermin.'
            );
        }

        try {
            $created = $this
                ->participantSyncService
                ->sync($schedule);

            if ($created === 0) {
                $this->addFlash(
                    'info',
                    'Es wurden keine neuen Terminbuchungen '
                    . 'angelegt. Alle aktiven Kursteilnehmer '
                    . 'sind bereits für diesen Termin eingetragen.'
                );
            } else {
                $this->addFlash(
                    'success',
                    sprintf(
                        '%d %s für den Kurstermin übernommen.',
                        $created,
                        $created === 1
                            ? 'Teilnehmer wurde'
                            : 'Teilnehmer wurden'
                    )
                );
            }
        } catch (\LogicException $exception) {
            $this->addFlash(
                'danger',
                $exception->getMessage()
            );
        }

        return $this->redirectToReferrer(
            $context
        );
    }

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
}