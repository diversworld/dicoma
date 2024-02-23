<?php

namespace App\Controller\Admin;

use App\Controller\BookingController;
use App\Entity\Booking;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class BookingCrudController extends AbstractCrudController
{
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager) {
        $this->entityManager = $entityManager;
    }

    public static function getEntityFqcn(): string
    {
        return Booking::class;
    }

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
