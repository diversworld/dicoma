<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\MemberTankCheckBooking;
use App\Entity\MemberTankCheckBookingItem;
use App\Entity\TuvHandover;
use App\Entity\TuvDocument;
use App\Entity\TuvGroupBooking;
use App\Entity\TuvNotification;
use App\Entity\MemberTankCheckResult;
use App\Entity\MemberTankCheckStatusHistory;
use App\Filter\TankOwnershipFilter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
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

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(ChoiceFilter::new('status', 'Bearbeitungsstatus')->setChoices([
                'Angemeldet' => 'requested',
                'Angenommen' => 'received',
                'Bei Prüfstelle' => 'sent',
                'Zurückerhalten' => 'returned',
                'Abgeschlossen' => 'completed',
                'Storniert' => 'cancelled',
            ]))
            ->add(TankOwnershipFilter::new('Flaschentyp'))
            ->add(EntityFilter::new('tankCheck', 'TÜV-Termin'))
            ->add(EntityFilter::new('clubTank', 'Vereinsflasche')
                ->setFormTypeOption('value_type_options.choice_label',
                    static fn (\App\Entity\Tank $tank): string =>
                        ($tank->getInventory() ?: '#'.$tank->getId()) . ' (ID '.$tank->getId().')'))
            ->add(EntityFilter::new('memberTank', 'Mitgliederflasche')
                ->setFormTypeOption('value_type_options.choice_label',
                    static fn (\App\Entity\MemberTank $tank): string =>
                        $tank->getLabel().' · '.$tank->getSerialNumber().' (ID '.$tank->getId().')'));
    }

    public function configureActions(Actions $actions): Actions
    {
        $advance = Action::new('advanceStatus', 'Nächster Schritt', 'fa fa-arrow-right')
            ->linkToCrudAction('confirmAdvance')
            ->displayIf(static fn (MemberTankCheckBooking $booking): bool => isset(self::NEXT[$booking->getStatus()]));

        $services = Action::new('bookingServices', 'Leistungen / Preise', 'fa fa-receipt')
            ->linkToCrudAction('bookingServices');

        $inspection = Action::new('inspectionResult', 'Prüfergebnis', 'fa fa-clipboard-check')
            ->linkToCrudAction('inspectionResult')
            ->displayIf(static fn (MemberTankCheckBooking $booking): bool =>
                in_array($booking->getStatus(), ['returned', 'completed'], true));

        $operations = Action::new('operations', 'Übergabe / Dokumente', 'fa fa-folder-open')->linkToCrudAction('operations');

        $clubRegistration = Action::new('clubRegistration', 'Vereinsflaschen anmelden', 'fa fa-plus-circle')
            ->createAsGlobalAction()
            ->linkToCrudAction('clubRegistration');

        return $actions
            ->add(Crud::PAGE_INDEX, $clubRegistration)
            ->disable(Action::NEW, Action::EDIT, Action::DELETE, Action::BATCH_DELETE)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $inspection)
            ->add(Crud::PAGE_DETAIL, $inspection)
            ->add(Crud::PAGE_INDEX, $operations)
            ->add(Crud::PAGE_DETAIL, $operations)
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

    public function clubRegistration(Request $request, EntityManagerInterface $em, AdminUrlGenerator $urls, \App\Service\TuvArticleSelector $selector): Response
    {
        $checks = $em->getRepository(\App\Entity\TankCheck::class)->findBy([], ['checkDate' => 'ASC']);
        $checks = array_values(array_filter($checks, static fn ($check): bool =>
            $check->isPublished() && $check->getCheckDate() !== null
            && $check->getCheckDate()->format('Y-m-d') >= date('Y-m-d')
            && ($check->getRegistrationDeadline() === null || $check->getRegistrationDeadline()->format('Y-m-d') >= date('Y-m-d'))));
        $tanks = $em->getRepository(\App\Entity\Tank::class)->findBy([], ['inventory' => 'ASC']);
        $selectedCheck = null;
        $selectedTank = null;
        $articles = [];
        $error = null;
        $checkId = $request->query->get('checkId');
        $tankId = $request->query->get('tankId');
        if (is_string($checkId) && ctype_digit($checkId) && is_string($tankId) && ctype_digit($tankId)) {
            $selectedCheck = $em->getRepository(\App\Entity\TankCheck::class)->find((int) $checkId);
            $selectedTank = $em->getRepository(\App\Entity\Tank::class)->find((int) $tankId);
            if ($selectedCheck && $selectedTank && in_array($selectedCheck, $checks, true)) {
                try {
                    foreach ($selector->eligibleForClubTank($selectedCheck, $selectedTank) as $article) {
                        $pair = \App\Service\TuvPriceCalculator::fromArticle($article);
                        $articles[] = ['article' => $article, 'mode' => $selector->effectiveMode($article), 'net' => $pair['net'], 'gross' => $pair['gross']];
                    }
                } catch (\DomainException $e) { $error = $e->getMessage(); }
            } else { $error = 'Termin oder Flasche nicht verfügbar.'; }
        }
        return $this->render('admin/tuv/club_registration.html.twig', [
            'checks' => $checks, 'tanks' => $tanks, 'selectedCheck' => $selectedCheck,
            'selectedTank' => $selectedTank, 'articles' => $articles, 'error' => $error,
            'backUrl' => $urls->unsetAll()->setController(self::class)->setAction(Action::INDEX)->generateUrl(),
            'formUrl' => $urls->unsetAll()->setController(self::class)->setAction('clubRegistration')->generateUrl(),
        ]);
    }

    public function operations(Request $request, EntityManagerInterface $em, AdminUrlGenerator $urls): Response
    {
        $rawId = $request->query->get('entityId');
        if (!is_string($rawId) || !ctype_digit($rawId) || (int)$rawId < 1) {
            throw $this->createNotFoundException('Ungültige Anmeldung');
        }
        $booking = $em->getRepository(MemberTankCheckBooking::class)->find((int)$rawId);
        if (!$booking) {
            throw $this->createNotFoundException('Anmeldung nicht gefunden');
        }
        $handovers = $em->getRepository(TuvHandover::class)->findBy(['booking'=>$booking], ['id'=>'DESC']);
        $docs = $em->getRepository(TuvDocument::class)->findBy(['booking'=>$booking], ['id'=>'DESC']);
        $groupLink = $em->getRepository(TuvGroupBooking::class)->findOneBy(['booking'=>$booking]);
        $members = $groupLink ? $em->getRepository(TuvGroupBooking::class)->findBy(['group'=>$groupLink->getGroup()]) : [];
        $notifications = $em->getRepository(TuvNotification::class)->findBy(['booking'=>$booking], ['id'=>'DESC']);
        $backUrl = $urls->unsetAll()->setController(self::class)->setAction(Action::INDEX)->generateUrl();
        return $this->render('admin/tuv/operations.html.twig', compact('booking','handovers','docs','groupLink','members','notifications','backUrl'));
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
        $inspection = $em->getRepository(MemberTankCheckResult::class)
            ->findOneBy(['booking' => $booking]);
        $inspectionUrl = null;
        if (in_array($booking->getStatus(), ['returned', 'completed'], true)) {
            $inspectionUrl = $adminUrlGenerator->unsetAll()
                ->setController(self::class)
                ->setAction('inspectionResult')
                ->setEntityId($booking->getId())
                ->generateUrl();
        }
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
            'inspection' => $inspection,
            'inspectionUrl' => $inspectionUrl,
            'history' => $history,
            'statusLabels' => self::LABELS,
            'rows' => $rows,
            'netCents' => $netCents,
            'grossCents' => $grossCents,
            'backUrl' => $backUrl,
        ]);
    }

    public function inspectionResult(Request $request, EntityManagerInterface $em, AdminUrlGenerator $adminUrlGenerator): Response
    {
        $rawId = $request->query->get('entityId');
        if (!is_string($rawId) || !ctype_digit($rawId) || (int) $rawId < 1) {
            throw $this->createNotFoundException('Ungültige TÜV-Anmeldung.');
        }

        $booking = $em->getRepository(MemberTankCheckBooking::class)->find((int) $rawId);
        if (!$booking instanceof MemberTankCheckBooking) {
            throw $this->createNotFoundException('TÜV-Anmeldung nicht gefunden.');
        }

        if (!in_array($booking->getStatus(), ['returned', 'completed'], true)) {
            $this->addFlash('warning', 'Prüfergebnisse können erst nach Rückerhalt erfasst werden.');
            return $this->redirect($adminUrlGenerator->unsetAll()->setController(self::class)->setAction(Action::INDEX)->generateUrl());
        }

        $result = $em->getRepository(MemberTankCheckResult::class)->findOneBy(['booking' => $booking]);
        $backUrl = $adminUrlGenerator->unsetAll()->setController(self::class)
            ->setAction(Action::INDEX)->generateUrl();
        $errors = [];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('tuv_inspection_result_'.$booking->getId(), (string) $request->request->get('_token', ''))) {
                throw $this->createAccessDeniedException('Ungültiger CSRF-Token.');
            }

            $choice = (string) $request->request->get('result', '');
            $dateRaw = (string) $request->request->get('inspected_at', '');
            $notes = trim((string) $request->request->get('notes', ''));
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $dateRaw);
            if (!in_array($choice, ['passed', 'failed'], true)) {
                $errors[] = 'Bitte ein gültiges Prüfergebnis wählen.';
            }
            if ($date === false || $date->format('Y-m-d') !== $dateRaw) {
                $errors[] = 'Bitte ein gültiges Prüfdatum eingeben.';
            } elseif ($date > new \DateTimeImmutable('today')) {
                $errors[] = 'Das Prüfdatum darf nicht in der Zukunft liegen.';
            }
            if (mb_strlen($notes) > 5000) {
                $errors[] = 'Die Bemerkung darf höchstens 5000 Zeichen enthalten.';
            }

            if ($errors === []) {
                $result ??= new MemberTankCheckResult($booking);
                $actor = $this->getUser()?->getUserIdentifier() ?? 'Unbekannt';
                $result->record($choice, $date, $notes !== '' ? $notes : null, $actor);
                $em->persist($result);
                $em->flush();
                $this->addFlash('success', 'TÜV-Prüfergebnis gespeichert.');
                return $this->redirect($backUrl);
            }
        }

        return $this->render('admin/tuv/inspection_result.html.twig', [
            'booking' => $booking,
            'inspection' => $result,
            'errors' => $errors,
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
