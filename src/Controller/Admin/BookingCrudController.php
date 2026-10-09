<?php

namespace App\Controller\Admin;

<<<<<<< HEAD
use App\Entity\Booking;
use App\Enum\BookingAttendanceStatus;
use App\Service\BookingNumberGenerator;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
=======
use App\Controller\BookingController;
use App\Entity\Booking;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
>>>>>>> origin/main
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
<<<<<<< HEAD
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class BookingCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly BookingNumberGenerator $bookingNumberGenerator
    ) {
=======
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class BookingCrudController extends AbstractCrudController
{
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager) {
        $this->entityManager = $entityManager;
>>>>>>> origin/main
    }

    public static function getEntityFqcn(): string
    {
        return Booking::class;
    }

<<<<<<< HEAD
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Terminbuchung')
            ->setEntityLabelInPlural('Terminbuchungen')
            ->setPageTitle(
                Crud::PAGE_INDEX,
                'Terminbuchungen'
            )
            ->setPageTitle(
                Crud::PAGE_NEW,
                'Terminbuchung erstellen'
            )
            ->setPageTitle(
                Crud::PAGE_EDIT,
                'Terminbuchung bearbeiten'
            )
            ->setPageTitle(
                Crud::PAGE_DETAIL,
                'Terminbuchung'
            )
            ->setDefaultSort([
                'bookingdate' => 'DESC',
            ])
            ->setSearchFields([
                'bookingnumber',
                'member.firstname',
                'member.lastname',
                'member.memberNumber',
                'status',
            ]);
    }

    public function configureFields(string $pageName): iterable
    {
        /*
         * Buchung
         */
        yield FormField::addFieldset('Buchung')
            ->collapsible();

        yield IdField::new(
            'id',
            'ID'
        )
            ->hideOnForm()
            ->setColumns(1);

        $bookingNumberField = TextField::new(
            'bookingnumber',
            'Buchungsnummer'
        )
            ->setColumns(2);

        /*
         * Nur bei einer neuen Buchung eine Nummer vorbelegen.
         */
        if ($pageName === Crud::PAGE_NEW) {
            $bookingNumberField->setFormTypeOption(
                'data',
                $this->bookingNumberGenerator->next()
            );
        }

        yield $bookingNumberField;

        yield DateField::new(
            'bookingdate',
            'Datum der Buchung'
        )
            ->setColumns(2)
            ->setFormat('dd.MM.yyyy');

        yield ChoiceField::new(
            'status',
            'Buchungsstatus'
        )
            ->setChoices([
                'Gebucht' => 'gebucht',
                'Bestätigt' => 'bestätigt',
                'Bezahlt' => 'bezahlt',
                'Storniert' => 'storniert',
            ])
            ->setColumns(2);

        /*
         * Kurstermin
         */
        yield FormField::addFieldset('Kurstermin')
            ->collapsible();

        yield AssociationField::new(
            'schedule',
            'Kurstermin'
        )
            ->setRequired(true)
            ->setColumns(5);

        /*
         * Mitglied
         */
        yield FormField::addFieldset('Mitglied')
            ->collapsible();

        yield AssociationField::new(
            'member',
            'Mitglied'
        )
            ->setRequired(true)
            ->setColumns(5)
            ->setHelp(
                'Mitglied, das für diesen Kurstermin gebucht ist.'
            );

        /*
         * Anwesenheit
         */
        yield FormField::addFieldset('Anwesenheit')
            ->collapsible();

        if ($pageName === Crud::PAGE_INDEX) {
            yield TextField::new(
                'attendanceStatusLabel',
                'Anwesenheit'
            );
        } else {
            yield ChoiceField::new(
                'attendanceStatus',
                'Anwesenheit'
            )
                ->setChoices(
                    BookingAttendanceStatus::choices()
                )
                ->setColumns(4);
        }

        yield DateTimeField::new(
            'attendanceRecordedAt',
            'Anwesenheit erfasst am'
        )
            ->hideOnForm();

        yield TextareaField::new(
            'attendanceNotes',
            'Anwesenheitsnotiz'
        )
            ->hideOnIndex()
            ->setColumns(6);
    }
}
=======
    public function configureFields(string $pageName): iterable
    {
        $entityManager = $this->entityManager;

        return [
            FormField::addFieldset('Buchung')
                ->collapsible(),
                IdField::new('id')
                    ->setColumns(1)
                    ->hideOnForm(),
            TextField::new('bookingnumber', 'Buchungsnummer')
                ->setColumns(2)
                ->setFormTypeOptions([
                    'data' => BookingController::nextBookingNumber( $entityManager),
                ]),
            DateField::new('bookingdate','Datum der Buchung')
                ->setColumns(2)
                ->setFormat('dd.MM.yyyy'),
            ChoiceField::new('status', 'Status')
                ->setChoices([
                    'gebucht' => 'gebucht',
                    'bestätigt' => 'bestätigt',
                    'bezahlt' => 'bezahlt',
                    'storniert' => 'storniert',
                ])
                ->setColumns(2),
            FormField::addFieldset('Kurstermin')
                ->collapsible(),
            AssociationField::new('schedule','Kurstermin')
                ->setColumns(4),
            FormField::addFieldset('Schüler')
                ->collapsible(),
            AssociationField::new('students', 'Schüler')
                ->setQueryBuilder(function (QueryBuilder $qb) {
                    return $qb
                        ->where('entity.category = :category')
                        ->setParameter('category', 'student')
                        ->orderBy('entity.lastname', 'ASC');
                })
                ->setColumns(5),
        ];
    }

    public function nextBookingNumber(): string
    {
        // Aktuelles Datum
        $currentDate = new \DateTime();

        $monthYear = $currentDate->format('Ym'); // Monat und Jahr der      Buchung
        $monthYearInt = intval($monthYear); // Monat und Jahr       als Integ   er

        // Buchungsnummer für den aktuellen Monat und Jahr erhalten
        $lastBooking = $this->entityManager->getRepository(Booking::class)->findOneBy([], ['id' => 'DESC']);
        $lastBookingMonthYear = $lastBooking ? $lastBooking->getBookingnumber() : null;

        if ($lastBookingMonthYear !== null && str_contains($lastBookingMonthYear, $monthYear)) {
            // Wenn bereits Buchungen für den aktuellen Monat existieren
            // Extrahiere die Nummer und erhöhe sie um 1
            $lastBookingNumber = intval(substr($lastBookingMonthYear, 6)); // Die letzten 4 Zeichen sind die Nummer
            $bookingNumber = $lastBookingNumber + 1;
        } else {
            // Wenn es keine Buchungen für den aktuellen Monat gibt, starte die Nummerierung von 1
            $bookingNumber = 1;
        }

        // Generiere die Buchungsnummer mit führenden Nullen
        $bookingNumberPadded = str_pad($bookingNumber, 4, '0', STR_PAD_LEFT);$bookingNr = $monthYearInt . $bookingNumberPadded;

        return $bookingNr;
    }
}
>>>>>>> origin/main
