<?php

namespace App\Controller\Admin;

use App\Entity\Booking;
use App\Entity\Courses;
use App\Controller\Admin;
use App\Filter\BookingCourseFilter;
use App\Repository\CoursesRepository;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
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
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;

#[IsGranted('ROLE_SUPER_ADMIN')]
class BookingCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly BookingNumberGenerator $bookingNumberGenerator,
        private readonly CoursesRepository $coursesRepository
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
                'id' => 'DESC',
            ])
            ->setSearchFields([
                'bookingnumber',
                'member.person.firstname',
                'member.person.lastname',
                'member.memberNumber',
                'status',
            ]);
    }

    public function configureFilters(Filters $filters): Filters
    {
        $courseChoices = [];
        foreach ($this->coursesRepository->findBy([], ['id' => 'DESC']) as $course) {
            $courseChoices[(string) $course] = (string) $course->getId();
        }

        return $filters
            ->add(BookingCourseFilter::new()->setFormTypeOption('choices', $courseChoices))
            ->add(EntityFilter::new('schedule', 'Kurstermin'))
            ->add(EntityFilter::new('member', 'Mitglied'))
            ->add(ChoiceFilter::new('status', 'Buchungsstatus')->setChoices([
                'Gebucht' => 'gebucht',
                'Bestätigt' => 'bestätigt',
                'Bezahlt' => 'bezahlt',
                'Storniert' => 'storniert',
            ]))
            ->add(ChoiceFilter::new('attendanceStatus', 'Anwesenheit')->setChoices(
                array_combine(
                    array_map(static fn (BookingAttendanceStatus $s) => $s->label(), BookingAttendanceStatus::cases()),
                    array_map(static fn (BookingAttendanceStatus $s) => $s->value, BookingAttendanceStatus::cases())
                )
            ));
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

        if ($pageName === Crud::PAGE_INDEX || $pageName === Crud::PAGE_DETAIL) {
            yield TextField::new('status', 'Buchungsstatus')
                ->formatValue(static fn ($value): string => self::statusBadge((string) ($value ?? '')))
                ->renderAsHtml();
        } else {
            yield ChoiceField::new('status', 'Buchungsstatus')
                ->setChoices([
                    'Gebucht' => 'gebucht',
                    'Bestätigt' => 'bestätigt',
                    'Bezahlt' => 'bezahlt',
                    'Storniert' => 'storniert',
                ])
                ->setColumns(2);
        }

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

        if ($pageName === Crud::PAGE_INDEX || $pageName === Crud::PAGE_DETAIL) {
            yield TextField::new('attendanceStatusLabel', 'Anwesenheit')
                ->formatValue(static function ($value, Booking $booking): string {
                    return self::attendanceBadge(
                        $booking->getAttendanceStatus()->value,
                        (string) ($value ?? '')
                    );
                })
                ->renderAsHtml();
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

    private static function statusBadge(string $value): string
    {
        $labels = [
            'gebucht' => ['Gebucht', 'primary'],
            'bestätigt' => ['Bestätigt', 'success'],
            'bezahlt' => ['Bezahlt', 'success'],
            'storniert' => ['Storniert', 'secondary'],
        ];
        [$label, $color] = $labels[$value] ?? [$value !== '' ? $value : 'Nicht gesetzt', 'secondary'];

        return self::badge($label, $color);
    }

    private static function attendanceBadge(string $value, string $label): string
    {
        $colors = [
            'planned' => 'secondary',
            'present' => 'success',
            'excused' => 'info',
            'absent' => 'danger',
            'makeup_required' => 'warning',
        ];

        return self::badge($label !== '' ? $label : $value, $colors[$value] ?? 'secondary');
    }

    private static function badge(string $label, string $color): string
    {
        return sprintf(
            '<span class="badge bg-%s">%s</span>',
            $color,
            htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        );
    }
}