<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\EquipmentReservation;
use App\Service\EquipmentReservationService;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class EquipmentReservationCrudController extends AbstractCrudController
{
    public function __construct(private readonly EquipmentReservationService $reservations) {}
    public static function getEntityFqcn(): string { return EquipmentReservation::class; }
    public function configureCrud(Crud $crud): Crud
    {
        return $crud->setEntityLabelInSingular('Ausrüstungsreservierung')
            ->setEntityLabelInPlural('Ausrüstungsreservierungen')
            ->setDefaultSort(['startsAt' => 'DESC']);
    }
    // Loeschen ueber EasyAdmin wuerde die Ausleihhistorie umgehen.
    public function configureActions(Actions $actions): Actions
    {
        return $actions->disable(Action::DELETE, Action::BATCH_DELETE);
    }

    public function configureFields(string $pageName): iterable
    {
        yield AssociationField::new('equipmentItem', 'Ausrüstung')->setRequired(true);
        yield AssociationField::new('member', 'Mitglied')->setRequired(true);
        yield DateTimeField::new('startsAt', 'Beginn')->setRequired(true);
        yield DateTimeField::new('endsAt', 'Ende')->setRequired(true);
        yield IntegerField::new('quantity', 'Menge')->setRequired(true);
        yield ChoiceField::new('status', 'Status')
            ->setChoices(['Reserviert' => 'reserved', 'Storniert' => 'cancelled'])
            ->onlyOnIndex();
        yield TextareaField::new('notes', 'Notizen')->hideOnIndex();
        yield DateTimeField::new('issuedAt', 'Ausgegeben am')->onlyOnDetail();
        yield DateTimeField::new('returnedAt', 'Zurueckgenommen am')->onlyOnDetail();
        yield ChoiceField::new('conditionAtReturn', 'Rueckgabezustand')
            ->setChoices(['Einsatzbereit' => 'ready', 'Wartung' => 'maintenance', 'Beschaedigt' => 'damaged'])
            ->onlyOnDetail();
        yield TextareaField::new('returnNotes', 'Rueckgabenotiz')->onlyOnDetail();
        yield Field::new('loanActions', 'Verleihaktionen')
            ->setTemplatePath('admin/equipment/loan_actions.html.twig')
            ->onlyOnDetail();
    }
    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if (!$entityInstance instanceof EquipmentReservation) { throw new \InvalidArgumentException('Ungültige Reservierung.'); }
        $this->reservations->save($entityInstance);
    }
    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if (!$entityInstance instanceof EquipmentReservation) { throw new \InvalidArgumentException('Ungültige Reservierung.'); }
        if ($entityInstance->getStatus() !== 'reserved') { throw new \DomainException('Nur aktive Reservierungen können bearbeitet werden.'); }
        $this->reservations->save($entityInstance);
    }
}
