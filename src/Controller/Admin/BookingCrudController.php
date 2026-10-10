<?php

namespace App\Controller\Admin;

use App\Entity\Booking;
use App\Enum\BookingAttendanceStatus;
use App\Service\BookingNumberGenerator;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
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
    }

    public static function getEntityFqcn(): string
    {
        return Booking::class;
    }

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