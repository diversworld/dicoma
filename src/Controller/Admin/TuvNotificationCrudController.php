<?php
declare(strict_types=1);
namespace App\Controller\Admin;

use App\Entity\TuvNotification;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_SUPER_ADMIN')]
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
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, Action::new('confirmSendNow', 'Jetzt senden', 'fa fa-paper-plane')->linkToCrudAction('confirmSendNow')->displayIf(static fn (TuvNotification $item): bool => $item->getState() === 'pending'))
            ->add(Crud::PAGE_DETAIL, Action::new('confirmSendNowDetail', 'Jetzt senden', 'fa fa-paper-plane')->linkToCrudAction('confirmSendNow')->displayIf(static fn (TuvNotification $item): bool => $item->getState() === 'pending'))
            ->add(Crud::PAGE_INDEX, Action::new('confirmRetry', 'Erneut senden', 'fa fa-redo')
                ->linkToCrudAction('confirmRetry')
                ->displayIf(static fn (TuvNotification $item): bool => $item->getState() === 'failed'))
            ->add(Crud::PAGE_DETAIL, Action::new('confirmRetryDetail', 'Erneut senden', 'fa fa-redo')
                ->linkToCrudAction('confirmRetry')
                ->displayIf(static fn (TuvNotification $item): bool => $item->getState() === 'failed'));
    }

    public function confirmSendNow(Request $request, EntityManagerInterface $em, AdminUrlGenerator $urls): Response
    {
        $id = filter_var($request->query->get('entityId'), FILTER_VALIDATE_INT);
        $item = $id && $id > 0 ? $em->find(TuvNotification::class, $id) : null;
        if (!$item instanceof TuvNotification || $item->getState() !== 'pending') {
            throw $this->createNotFoundException('Nur ausstehende Nachrichten können versendet werden.');
        }
        return $this->render('admin/tuv/notification_send_confirm.html.twig', [
            'notification' => $item,
            'post_url' => $this->generateUrl('admin_tuv_notification_send_now', ['id' => $item->getId()]),
            'back_url' => $urls->setController(self::class)->setAction(Action::DETAIL)->setEntityId($item->getId())->generateUrl(),
        ]);
    }

    public function confirmRetry(Request $request, EntityManagerInterface $em, AdminUrlGenerator $urls): Response
    {
        $id = filter_var($request->query->get('entityId'), FILTER_VALIDATE_INT);
        $item = $id && $id > 0 ? $em->find(TuvNotification::class, $id) : null;
        if (!$item instanceof TuvNotification || $item->getState() !== 'failed') {
            throw $this->createNotFoundException('Nur fehlgeschlagene Nachrichten können erneut vorgemerkt werden.');
        }

        return $this->render('admin/tuv/notification_retry_confirm.html.twig', [
            'notification' => $item,
            'post_url' => $this->generateUrl('admin_tuv_notification_retry_post', ['id' => $item->getId()]),
            'back_url' => $urls->setController(self::class)->setAction(Action::DETAIL)
                ->setEntityId($item->getId())->generateUrl(),
        ]);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters->add(ChoiceFilter::new('state', 'Versandstatus')
            ->setChoices(['Ausstehend' => 'pending', 'Versendet' => 'sent', 'Fehlgeschlagen' => 'failed']));
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', 'ID')->hideOnForm();
        yield TextField::new('bookingNumber', 'Anmeldung')->onlyOnDetail();
        yield TextField::new('recipient', 'Empfänger');
        yield TextField::new('subject', 'Betreff');
        yield TextField::new('state', 'Status');
        yield \EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField::new('attemptCount', 'Versuche')->onlyOnDetail();
        yield DateTimeField::new('lastAttemptAt', 'Letzter Versuch')->onlyOnDetail();
        yield TextareaField::new('lastError', 'Letzter Fehler')->onlyOnDetail();
        yield DateTimeField::new('createdAt', 'Vorgemerkt');
        yield DateTimeField::new('sentAt', 'Versendet');
        yield TextField::new('dedupeKey', 'Eindeutiger Schlüssel')->onlyOnDetail();
        yield TextareaField::new('body', 'Nachrichtentext')->onlyOnDetail();
    }
}
