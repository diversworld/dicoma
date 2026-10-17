<?php
declare(strict_types=1);
namespace App\Controller\Admin;

use App\Entity\TuvNotification;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class TuvNotificationCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string { return TuvNotification::class; }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud->setEntityLabelInSingular('TÜV-Nachricht')
            ->setEntityLabelInPlural('TÜV-Nachrichten / Postausgang')
            ->setDefaultSort(['id' => 'DESC'])
            ->setSearchFields(['recipient', 'subject', 'dedupeKey', 'body']);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions->disable(Action::NEW, Action::EDIT, Action::DELETE)
            ->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters->add(ChoiceFilter::new('state', 'Versandstatus')
            ->setChoices(['Ausstehend' => 'pending', 'Versendet' => 'sent']));
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', 'ID')->hideOnForm();
        yield TextField::new('bookingNumber', 'Anmeldung')->onlyOnDetail();
        yield TextField::new('recipient', 'Empfänger');
        yield TextField::new('subject', 'Betreff');
        yield TextField::new('state', 'Status');
        yield DateTimeField::new('createdAt', 'Vorgemerkt');
        yield DateTimeField::new('sentAt', 'Versendet');
        yield TextField::new('dedupeKey', 'Eindeutiger Schlüssel')->onlyOnDetail();
        yield TextareaField::new('body', 'Nachrichtentext')->onlyOnDetail();
    }
}
