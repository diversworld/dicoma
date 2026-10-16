<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Tank;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Entity\TankConditionHistory;
use App\Entity\TankMaintenanceOrder;
use App\Service\TankMaintenanceService;
use App\Service\TankStatusWorkflow;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[IsGranted('ROLE_ADMIN')]
class TankCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly AdminUrlGenerator $adminUrls,
        private readonly EntityManagerInterface $em,
        private readonly TankStatusWorkflow $statusWorkflow,
        private readonly TankMaintenanceService $maintenanceService,
    ) {}

    public static function getEntityFqcn(): string { return Tank::class; }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Tauchflasche')
            ->setEntityLabelInPlural('Tauchflaschen')
            ->setDefaultSort(['inventory' => 'ASC'])
            ->setSearchFields(['inventory', 'serialnumber']);
    }

    public function configureActions(Actions $actions): Actions
    {
        // EasyAdmin supplies the entity ID and AdminContext for custom CRUD actions.
        // Do not generate a separate Symfony route or depend on RequestStack.
        $status = Action::new('tankStatus', 'Zustand / Historie', 'fa fa-shield-alt')
            ->linkToCrudAction('tankStatus');
        $maintenance = Action::new('tankMaintenance', 'Wartungsaufträge', 'fa fa-wrench')
            ->linkToCrudAction('tankMaintenance');
        return $actions->add(Crud::PAGE_INDEX, $status)->add(Crud::PAGE_DETAIL, $status)
            ->add(Crud::PAGE_INDEX, $maintenance)->add(Crud::PAGE_DETAIL, $maintenance);
    }

    #[AdminRoute(path: '/status', name: 'status')]
    public function tankStatus(AdminContext $context, Request $request): Response
    {
        $entity = $context->getEntity()?->getInstance();
        if (!$entity instanceof Tank || null === $entity->getId()) {
            throw $this->createNotFoundException('Tauchflasche nicht gefunden.');
        }
        $id = $entity->getId();
        if (!$request->isMethod('GET') && !$request->isMethod('POST')) {
            throw $this->createNotFoundException('Methode nicht unterstützt.');
        }
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('tank_status_'.$id, (string) $request->request->get('_token', ''))) {
                throw $this->createAccessDeniedException('Ungültiger CSRF-Token.');
            }
            try {
                $actor = $this->getUser()?->getUserIdentifier() ?? 'unbekannt';
                $this->statusWorkflow->apply(
                    $id,
                    (string) $request->request->get('operation', ''),
                    (string) $request->request->get('reason', ''),
                    $actor,
                );
                $this->addFlash('success', 'Statusänderung protokolliert.');
            } catch (\DomainException $exception) {
                $this->addFlash('danger', $exception->getMessage());
            }
            return $this->redirect($this->statusUrl($id), Response::HTTP_SEE_OTHER);
        }

        $history = $this->em->getRepository(TankConditionHistory::class)->findBy(
            ['tank' => $entity], ['occurredAt' => 'DESC', 'id' => 'DESC'], 100,
        );
        return $this->render('admin/tank/status.html.twig', [
            'tank' => $entity,
            'history' => $history,
            'statusActionUrl' => $this->statusUrl($id),
            'detailUrl' => (clone $this->adminUrls)->unsetAll()
                ->setController(self::class)->setAction(Crud::PAGE_DETAIL)
                ->setEntityId($id)->generateUrl(),
        ]);
    }

    #[AdminRoute(path: '/maintenance', name: 'maintenance')]
    public function tankMaintenance(AdminContext $context, Request $request): Response
    {
        $tank = $context->getEntity()?->getInstance();
        if (!$tank instanceof Tank || null === $tank->getId()) {
            throw $this->createNotFoundException('Tauchflasche nicht gefunden.');
        }
        $tankId = $tank->getId();
        if (!$request->isMethod('GET') && !$request->isMethod('POST')) {
            throw $this->createNotFoundException('Methode nicht unterstützt.');
        }
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('tank_maintenance_'.$tankId, (string) $request->request->get('_token', ''))) {
                throw $this->createAccessDeniedException('Ungültiger CSRF-Token.');
            }
            try {
                $actor = $this->getUser()?->getUserIdentifier() ?? 'unbekannt';
                $operation = (string) $request->request->get('operation', '');
                if ($operation === 'open') {
                    $this->maintenanceService->open($tankId, (string) $request->request->get('description', ''), $actor);
                    $this->addFlash('success', 'Wartungsauftrag eröffnet; Flasche gesperrt.');
                } elseif ($operation === 'close') {
                    $orderId = filter_var($request->request->get('order_id'), FILTER_VALIDATE_INT);
                    if (!$orderId) {
                        throw new \DomainException('Ungültige Auftragsnummer.');
                    }
                    $this->maintenanceService->closeForTank($tankId, $orderId, (string) $request->request->get('resolution', ''), $actor);
                    $this->addFlash('success', 'Wartungsauftrag abgeschlossen; Flasche bleibt gesperrt.');
                } else {
                    throw new \DomainException('Unbekannte Wartungsaktion.');
                }
            } catch (\DomainException $exception) {
                $this->addFlash('danger', $exception->getMessage());
            }
            return $this->redirect($this->maintenanceUrl($tankId), Response::HTTP_SEE_OTHER);
        }

        $orders = $this->em->getRepository(TankMaintenanceOrder::class)->findBy(
            ['tank' => $tank], ['createdAt' => 'DESC', 'id' => 'DESC'], 100,
        );
        return $this->render('admin/tank/maintenance.html.twig', [
            'tank' => $tank,
            'orders' => $orders,
            'maintenanceUrl' => $this->maintenanceUrl($tankId),
            'statusUrl' => $this->statusUrl($tankId),
        ]);
    }

    private function maintenanceUrl(int $id): string
    {
        return (clone $this->adminUrls)->unsetAll()
            ->setController(self::class)->setAction('tankMaintenance')
            ->setEntityId($id)->generateUrl();
    }

    private function statusUrl(int $id): string
    {
        return (clone $this->adminUrls)->unsetAll()
            ->setController(self::class)->setAction('tankStatus')
            ->setEntityId($id)->generateUrl();
    }

    public function configureFields(string $pageName): iterable
    {
        // DiCoMa STEP85 INDEX: dedicated, consistent overview columns

        if ($pageName === Crud::PAGE_INDEX) {
            yield TextField::new('inventory', 'Inventarnummer');
            yield AssociationField::new('club', 'Verein');
            yield TextField::new('serialnumber', 'Seriennummer');
            yield ChoiceField::new('size', 'Volumen')->setChoices([
                '3 L' => 3, '5 L' => 5, '7 L' => 7, '8 L' => 8,
                '10 L' => 10, '12 L' => 12, '15 L' => 15,
                '18 L' => 18, '20 L' => 20,
            ]);
            yield DateField::new('nextCheckDate', 'Nächste Prüfung')->setFormat('dd.MM.yyyy');
            yield BooleanField::new('oxigenClean', 'O₂-rein');
            yield ChoiceField::new('conditionStatus', 'Technischer Zustand')->setChoices(['Einsatzbereit' => 'ready', 'Wartung' => 'maintenance', 'Beschädigt' => 'damaged']);
            yield BooleanField::new('lendable', 'Verleihfreigabe')->renderAsSwitch(false)->setDisabled(true);
            return;
        }

        yield FormField::addFieldset('Inventar und Verein');
        yield IdField::new('id', 'ID')->onlyOnIndex();
        yield AssociationField::new('club', 'Verein')->setRequired(true)->setColumns(4);
        yield TextField::new('inventory', 'Inventarnummer')->setRequired(true)->setColumns(4);
        yield TextField::new('serialnumber', 'Seriennummer')->setRequired(true)->setColumns(4);

        yield FormField::addFieldset('Flaschendaten')->collapsible();
        yield ChoiceField::new('size', 'Flaschengröße')
            ->setChoices(['3 L' => 3, '5 L' => 5, '7 L' => 7, '8 L' => 8, '10 L' => 10,
                '12 L' => 12, '15 L' => 15, '18 L' => 18, '20 L' => 20])
            ->setRequired(true)->setColumns(4);
        yield BooleanField::new('oxigenClean', 'O₂-rein')->setColumns(4);
        yield DateField::new('buyDate', 'Kaufdatum')->setFormat('dd.MM.yyyy')->setColumns(4);

        yield FormField::addFieldset('Prüfungen')->collapsible();
        yield DateField::new('lastCheckDate', 'Letzte Prüfung')->setRequired(true)
            ->setFormat('dd.MM.yyyy')->setColumns(4);
        yield DateField::new('nextCheckDate', 'Nächste Prüfung')->setFormat('dd.MM.yyyy')
            ->setColumns(4)->setHelp('Für die Verleihfreigabe wird die Prüfungsfrist berücksichtigt.');
        yield AssociationField::new('tankChecks', 'Prüfungszuordnungen')->onlyOnDetail();

        yield FormField::addFieldset('Technischer Zustand und Verleih')->collapsible();
        yield ChoiceField::new('conditionStatus', 'Technischer Zustand')
            ->setChoices(['Einsatzbereit' => 'ready', 'Wartung' => 'maintenance', 'Beschädigt' => 'damaged'])
            ->hideOnIndex()->hideWhenCreating()->setFormTypeOption('disabled', true)
            ->setHelp('Schreibgeschützt. Änderungen über Zustand / Historie vornehmen.');
        yield BooleanField::new('lendable', 'Verleihfreigabe')
            ->renderAsSwitch(false)->setDisabled(true)->hideOnIndex()->hideWhenCreating();

        yield FormField::addFieldset('Bemerkungen')->collapsible();
        yield TextareaField::new('notes', 'Notizen')->hideOnIndex()->setColumns(12);
    }
}
