<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\EquipmentBooking;
use App\Form\EquipmentBookingItemType;
use App\Service\EquipmentBookingService;
use App\Service\EquipmentBookingLoanService;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class EquipmentBookingCrudController extends AbstractCrudController
{
    use EquipmentBookingBasketTrait;

    // Auch direkte EasyAdmin-NEW-URLs fuehren zur Mehrfachauswahl.
    public function new(AdminContext $context): Response
    {
        return new RedirectResponse((clone $this->adminUrlGenerator)->unsetAll()
            ->setRoute('admin_equipment_booking_basket')->generateUrl(), 303);
    }

    public function __construct(private readonly EquipmentBookingService $bookingService, private readonly AdminUrlGenerator $adminUrlGenerator, private readonly EquipmentBookingLoanService $loanService) {}
    public static function getEntityFqcn(): string { return EquipmentBooking::class; }
    public function configureCrud(Crud $crud): Crud
    {
        return $crud->setEntityLabelInSingular('Sammelreservierung')
            ->setEntityLabelInPlural('Sammelreservierungen')
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->overrideTemplate('crud/detail', 'admin/equipment/booking_detail.html.twig');
    }
	
    public function configureActions(Actions $actions): Actions
    {
        $basket = Action::new('equipmentBasket', 'Neue Reservierung', 'fa fa-plus')
            ->linkToUrl((clone $this->adminUrlGenerator)->unsetAll()
                ->setRoute('admin_equipment_booking_basket')->generateUrl())
            ->createAsGlobalAction();
        return $actions->disable(Action::NEW, Action::EDIT, Action::DELETE, Action::BATCH_DELETE)
            ->add(Crud::PAGE_INDEX, $basket)
            ->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    public function configureFields(string $pageName): iterable
    {
        yield AssociationField::new('member', 'Mitglied')->setRequired(true)->hideOnIndex();
        yield DateTimeField::new('startsAt', 'Beginn')->setRequired(true);
        yield DateTimeField::new('endsAt', 'Ende')->setRequired(true);
        yield TextField::new('status', 'Status')->hideOnForm();
        yield DateTimeField::new('createdAt', 'Angelegt')->onlyOnIndex();
        yield CollectionField::new('items', 'Ausrüstungspositionen')
            ->setEntryType(EquipmentBookingItemType::class)
            ->allowAdd()->allowDelete()->setFormTypeOption('by_reference', false)
            ->onlyOnForms();
        yield CollectionField::new('items', 'Positionen')->onlyOnDetail();
        yield TextareaField::new('notes', 'Notizen')->hideOnIndex();
    }
	
    #[AdminRoute(path: '/equipment-booking-item-action',name: 'equipment_booking_item_action')]
    public function equipmentBookingItemAction(Request $request): Response
    {
		if (!$request->isMethod('POST')) {
			throw $this->createNotFoundException(
				'Diese Aktion unterstützt ausschließlich POST.'
			);
		}
		
        if (!$this->isCsrfTokenValid('equipment_booking_item_action', (string) $request->request->get('_token', ''))) {
            throw $this->createAccessDeniedException('Ungueltiger CSRF-Token.');
        }
        
		$bookingId = filter_var($request->request->get('booking_id'), FILTER_VALIDATE_INT);
        $lineId = filter_var($request->request->get('line_id'), FILTER_VALIDATE_INT);
        
		if (!$bookingId || !$lineId) { throw $this->createNotFoundException(); }
        
		$operation = (string) $request->request->get('operation', '');
        
		try {
            $this->loanService->change(
                $bookingId,
                $lineId,
                $operation,
                $operation === 'return' ? (string) $request->request->get('condition', '') : null,
                $operation === 'return' ? trim((string) $request->request->get('notes', '')) : null,
            );
			
            $this->addFlash('success', 'Position aktualisiert.');
			
        } catch (\DomainException $e) {
            $this->addFlash('danger', $e->getMessage());
        }
		
        return new RedirectResponse((clone $this->adminUrlGenerator)->unsetAll()
            ->setController(self::class)->setAction(Crud::PAGE_DETAIL)
            ->setEntityId($bookingId)->generateUrl(), 303);
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if (!$entityInstance instanceof EquipmentBooking) { throw new \InvalidArgumentException('Ungültige Buchung.'); }
        $this->bookingService->saveNew($entityInstance);
    }
}
