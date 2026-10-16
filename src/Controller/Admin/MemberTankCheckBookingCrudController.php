<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\MemberTankCheckBooking;
use App\Entity\MemberTankCheckBookingItem;
use App\Entity\MemberTankCheckStatusHistory;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class MemberTankCheckBookingCrudController extends AbstractCrudController
{
    private const NEXT = [
        'requested' => 'received',
        'received' => 'sent',
        'sent' => 'returned',
        'returned' => 'completed',
    ];

    private const LABELS = [
        'requested' => 'Angemeldet',
        'received' => 'Angenommen',
        'sent' => 'Bei Prüfstelle',
        'returned' => 'Zurückerhalten',
        'completed' => 'Abgeschlossen',
        'cancelled' => 'Storniert',
    ];

    public static function getEntityFqcn(): string
    {
        return MemberTankCheckBooking::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud->setEntityLabelInSingular('TÜV-Anmeldung')
            ->setEntityLabelInPlural('TÜV-Anmeldungen')
            ->setDefaultSort(['createdAt' => 'DESC']);
    }

    public function configureActions(Actions $actions): Actions
    {
        $advance = Action::new('advanceStatus', 'Nächster Schritt', 'fa fa-arrow-right')
            ->linkToCrudAction('confirmAdvance')
            ->displayIf(static fn (MemberTankCheckBooking $booking): bool => isset(self::NEXT[$booking->getStatus()]));

        $services = Action::new('bookingServices', 'Leistungen / Preise', 'fa fa-receipt')
            ->linkToCrudAction('bookingServices');

        return $actions
            ->disable(Action::NEW, Action::EDIT, Action::DELETE, Action::BATCH_DELETE)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $services)
            ->add(Crud::PAGE_DETAIL, $services)
            ->add(Crud::PAGE_INDEX, $advance)
            ->add(Crud::PAGE_DETAIL, $advance);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnDetail();
        yield TextField::new('memberDisplay', 'Mitglieds-ID');
        yield TextField::new('tankDisplay', 'Flasche');
        yield TextField::new('checkDisplay', 'TÜV-Termin');
        yield TextField::new('status', 'Status')
            ->formatValue(static fn ($value): string => self::LABELS[(string) $value] ?? (string) $value);
        yield DateTimeField::new('createdAt', 'Angemeldet am');
        yield TextField::new('articleTitleSnapshot', 'Frühere Einzelposition')->onlyOnDetail();
        yield TextField::new('priceSnapshot', 'Früherer Preis-Snapshot')->onlyOnDetail();
    }

    public function bookingServices(Request $request, EntityManagerInterface $em, AdminUrlGenerator $adminUrlGenerator): Response
    {
        // Custom CRUD action: die ID explizit laden, unabhängig vom Entity-Kontext.
        $rawId = $request->query->get('entityId');
        if (!is_string($rawId) || !ctype_digit($rawId) || (int) $rawId < 1) {
            throw $this->createNotFoundException('Ungültige TÜV-Anmeldung.');
        }
        $booking = $em->getRepository(MemberTankCheckBooking::class)->find((int) $rawId);
        if (!$booking instanceof MemberTankCheckBooking) {
            throw $this->createNotFoundException('TÜV-Anmeldung nicht gefunden.');
        }

        $items = $em->getRepository(MemberTankCheckBookingItem::class)
            ->findBy(['booking' => $booking], ['id' => 'ASC']);
        $history = $em->getRepository(MemberTankCheckStatusHistory::class)
            ->findBy(['booking' => $booking], ['changedAt' => 'ASC', 'id' => 'ASC']);
        $rows = [];
        $netCents = 0;
        $grossCents = 0;
        foreach ($items as $item) {
            $quantity = $item->getQuantity();
            $net = $this->toCents($item->getNetSnapshot());
            $gross = $this->toCents($item->getGrossSnapshot());
            $rows[] = [
                'title' => $item->getTitleSnapshot(),
                'quantity' => $quantity,
                'net' => $net,
                'gross' => $gross,
                'netTotal' => $net * $quantity,
                'grossTotal' => $gross * $quantity,
            ];
            $netCents += $net * $quantity;
            $grossCents += $gross * $quantity;
        }

        $backUrl = $adminUrlGenerator->setController(self::class)
            ->setAction(Action::INDEX)->unset('entityId')->generateUrl();

        return $this->render('admin/tuv/booking_services.html.twig', [
            'booking' => $booking,
            'history' => $history,
            'statusLabels' => self::LABELS,
            'rows' => $rows,
            'netCents' => $netCents,
            'grossCents' => $grossCents,
            'backUrl' => $backUrl,
        ]);
    }

    private function toCents(string $decimal): int
    {
        if (!preg_match('/^-?\d+\.\d{2}$/D', $decimal)) {
            throw new \UnexpectedValueException('Ungültiger gespeicherter Preis.');
        }
        $negative = str_starts_with($decimal, '-');
        [$whole, $fraction] = explode('.', ltrim($decimal, '-'), 2);
        $cents = ((int) $whole * 100) + (int) $fraction;
        return $negative ? -$cents : $cents;
    }

    public function confirmAdvance(Request $request, EntityManagerInterface $em, AdminUrlGenerator $adminUrlGenerator): Response
    {
        // EasyAdmin custom CRUD actions pass the record ID as query parameter.
        // AdminContext::getEntity() is unavailable outside an entity CRUD context.
        $rawId = $request->query->get('entityId');
        if (!is_string($rawId) || !ctype_digit($rawId) || (int) $rawId < 1) {
            throw $this->createNotFoundException('Ungültige TÜV-Anmeldung.');
        }
        $booking = $em->getRepository(MemberTankCheckBooking::class)->find((int) $rawId);
        if (!$booking instanceof MemberTankCheckBooking) {
            throw $this->createNotFoundException('TÜV-Anmeldung nicht gefunden.');
        }
        $next = self::NEXT[$booking->getStatus()] ?? null;
        if ($next === null) {
            $this->addFlash('warning', 'Für diese Anmeldung ist kein weiterer Schritt möglich.');
            return $this->redirect($adminUrlGenerator->unsetAll()->setController(self::class)->setAction(Action::INDEX)->generateUrl());
        }

        return $this->render('admin/tuv/confirm_advance.html.twig', [
            'booking' => $booking,
            'next' => $next,
            'nextLabel' => self::LABELS[$next],
            'backUrl' => $adminUrlGenerator->unsetAll()->setController(self::class)->setAction(Action::INDEX)->generateUrl(),
        ]);
    }
}
