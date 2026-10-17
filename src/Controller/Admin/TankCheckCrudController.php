<?php

namespace App\Controller\Admin;

use App\Entity\TankCheck;
use App\Entity\MemberTankCheckBooking;
use App\Entity\MemberTankCheckBookingItem;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Service\TuvXlsxExporter;
use App\Form\TankCheckArticleType;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

#[IsGranted('ROLE_ADMIN')]
class TankCheckCrudController extends AbstractCrudController
{
    public function configureActions(Actions $actions): Actions
    {
        $summary = Action::new('tuvSummary', 'Anmeldungen / Kosten', 'fa fa-chart-bar')
            ->linkToCrudAction('tuvSummary');

        $print = Action::new('printTuvList', 'Sammelliste drucken', 'fa fa-print')
            ->linkToCrudAction('printTuvList');

        $csv = Action::new('exportTuvCsv', 'Sammelliste CSV', 'fa fa-file-csv')
            ->linkToCrudAction('exportTuvCsv');

        $xlsx = Action::new('exportTuvXlsx', 'Sammelliste XLSX', 'fa fa-file-excel')
            ->linkToCrudAction('exportTuvXlsx');

        return $actions->add(Crud::PAGE_INDEX, $xlsx)
            ->add(Crud::PAGE_DETAIL, $xlsx)
            ->add(Crud::PAGE_INDEX, $csv)
            ->add(Crud::PAGE_DETAIL, $csv)
            ->add(Crud::PAGE_INDEX, $summary)
            ->add(Crud::PAGE_DETAIL, $summary)
            ->add(Crud::PAGE_INDEX, $print)
            ->add(Crud::PAGE_DETAIL, $print);
    }

    public function tuvSummary(Request $request, EntityManagerInterface $em, AdminUrlGenerator $urls): Response
    {
        return $this->renderCheckReport($request, $em, $urls, 'summary');
    }

    public function printTuvList(Request $request, EntityManagerInterface $em, AdminUrlGenerator $urls): Response
    {
        return $this->renderCheckReport($request, $em, $urls, 'print');
    }

    public function exportTuvCsv(Request $request, EntityManagerInterface $em, AdminUrlGenerator $urls): Response
    {
        return $this->renderCheckReport($request, $em, $urls, 'csv');
    }

    public function exportTuvXlsx(Request $request, EntityManagerInterface $em, AdminUrlGenerator $urls, TuvXlsxExporter $exporter): Response
    {
        return $this->renderCheckReport($request, $em, $urls, 'xlsx', $exporter);
    }

    private function renderCheckReport(Request $request, EntityManagerInterface $em, AdminUrlGenerator $urls, string $mode, ?TuvXlsxExporter $exporter = null): Response
    {
        $rawId = $request->query->get('entityId');
        if (!is_string($rawId) || !ctype_digit($rawId) || (int) $rawId < 1) {
            throw $this->createNotFoundException('Ungültiger TÜV-Termin.');
        }
        $check = $em->getRepository(TankCheck::class)->find((int) $rawId);
        if (!$check instanceof TankCheck) {
            throw $this->createNotFoundException('TÜV-Termin nicht gefunden.');
        }

        $bookings = $em->getRepository(MemberTankCheckBooking::class)
            ->findBy(['tankCheck' => $check], ['id' => 'ASC']);
        $rows = [];
        $counts = ['club' => 0, 'member' => 0];
        $netCents = 0;
        $grossCents = 0;
        foreach ($bookings as $booking) {
            $type = $booking->isClubTankBooking() ? 'club' : 'member';
            ++$counts[$type];
            $rowNet = 0;
            $rowGross = 0;
            $items = $em->getRepository(MemberTankCheckBookingItem::class)
                ->findBy(['booking' => $booking]);
            foreach ($items as $item) {
                $quantity = $item->getQuantity();
                $rowNet += self::decimalToCents($item->getNetSnapshot()) * $quantity;
                $rowGross += self::decimalToCents($item->getGrossSnapshot()) * $quantity;
            }
            $netCents += $rowNet;
            $grossCents += $rowGross;
            $tank = $booking->isClubTankBooking()
                ? $booking->getClubTank()
                : $booking->getMemberTank();
            $size = $booking->isClubTankBooking()
                ? $tank?->getSize()
                : ($tank?->getWaterCapacityLiters() ?: $tank?->getVolume());
            $serial = $booking->isClubTankBooking()
                ? $tank?->getSerialnumber()
                : $tank?->getSerialNumber();
            $sizeLabel = $size !== null && trim((string) $size) !== ''
                ? trim((string) $size) . ($booking->isClubTankBooking() || $tank?->getWaterCapacityLiters() ? ' L' : '')
                : '–';
            $serialLabel = $serial !== null && trim((string) $serial) !== ''
                ? trim((string) $serial)
                : '–';
            $rows[] = [
                'size' => $sizeLabel,
                'serial' => $serialLabel,
                'id' => $booking->getId(),
                'type' => $type,
                'tank' => $booking->getTankDisplay(),
                'status' => $booking->getStatus(),
                'netCents' => $rowNet,
                'grossCents' => $rowGross,
                'hasItems' => count($items) > 0,
                'url' => $urls->unsetAll()
                    ->setController(MemberTankCheckBookingCrudController::class)
                    ->setAction('bookingServices')->setEntityId($booking->getId())->generateUrl(),
            ];
        }

        if ($mode === 'xlsx') {
            if ($exporter === null) {
                throw new \LogicException('XLSX-Exporter fehlt.');
            }
            $filename = sprintf('tuv-sammelliste-%s-termin-%d.xlsx', $check->getCheckDate()->format('Y-m-d'), $check->getId());
            return $exporter->download($rows, $counts, $netCents, $grossCents, $filename);
        }

        if ($mode === 'csv') {
            $filename = sprintf('tuv-sammelliste-%s-termin-%d.csv', $check->getCheckDate()->format('Y-m-d'), $check->getId());
            $response = new StreamedResponse(static function () use ($rows, $counts, $netCents, $grossCents): void {
                $out = fopen('php://output', 'wb');
                if ($out === false) {
                    throw new \RuntimeException('CSV-Ausgabe konnte nicht geöffnet werden.');
                }
                // UTF-8 BOM: Excel erkennt damit Umlaute in der CSV.
                fwrite($out, "\xEF\xBB\xBF");
                fputcsv($out, ['Anmeldung', 'Flaschentyp', 'Flasche', 'Größe', 'Seriennummer', 'Status', 'Netto EUR', 'Brutto EUR', 'Übergabe', 'Bemerkung'], ';');
                foreach ($rows as $row) {
                    fputcsv($out, [
                        $row['id'],
                        $row['type'] === 'club' ? 'Verein' : 'Mitglied',
                        self::csvSafe($row['tank']),
                        self::csvSafe($row['size']),
                        self::csvSafe($row['serial']),
                        self::csvSafe($row['status']),
                        self::centsToDecimal($row['netCents']),
                        self::centsToDecimal($row['grossCents']),
                        '',
                        '',
                    ], ';');
                }
                fputcsv($out, [], ';');
                fputcsv($out, ['Gesamt', 'Verein: '.$counts['club'].' / Mitglied: '.$counts['member'], '', '', '', '', self::centsToDecimal($netCents), self::centsToDecimal($grossCents), '', ''], ';');
                fclose($out);
            });
            $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
            $response->headers->set('Content-Disposition', $response->headers->makeDisposition('attachment', $filename));
            $response->headers->set('Cache-Control', 'private, no-store');
            return $response;
        }

        return $this->render($mode === 'print' ? 'admin/tuv/check_print.html.twig' : 'admin/tuv/check_summary.html.twig', [
            'check' => $check,
            'rows' => $rows,
            'counts' => $counts,
            'netCents' => $netCents,
            'grossCents' => $grossCents,
            'generatedAt' => new \DateTimeImmutable(),
            'backUrl' => $urls->unsetAll()->setController(self::class)
                ->setAction(Action::INDEX)->generateUrl(),
        ]);
    }

    private static function centsToDecimal(int $cents): string
    {
        $negative = $cents < 0 ? '-' : '';
        $absolute = abs($cents);
        return $negative.intdiv($absolute, 100).','.str_pad((string) ($absolute % 100), 2, '0', STR_PAD_LEFT);
    }

    private static function csvSafe(string $value): string
    {
        // Tabellenkalkulationen dürfen Nutzerdaten nicht als Formel ausführen.
        return preg_match('/^[\\x00-\\x20]*[=+@\\-]/', $value) ? "'".$value : $value;
    }

    private static function decimalToCents(string $value): int
    {
        if (!preg_match('/^-?\d+\.\d{2}$/D', $value)) {
            throw new \UnexpectedValueException('Ungültiger gespeicherter Preis.');
        }
        $negative = str_starts_with($value, '-');
        [$whole, $fraction] = explode('.', ltrim($value, '-'), 2);
        $cents = (int) $whole * 100 + (int) $fraction;
        return $negative ? -$cents : $cents;
    }

    public static function getEntityFqcn(): string
    {
        return TankCheck::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            FormField::addFieldset('Termininformationen')
                ->collapsible(),
            BooleanField::new('isPublished', 'Für Mitglieder veröffentlicht'),
            IdField::new('id')
                ->hideOnForm()
                ->setColumns(3),
            DateField::new('checkDate','Prüfdatum')
                ->setColumns(2)
                ->setFormat('dd.MM.yyyy'),
            FormField::addFieldset('Prüferinformationen')
            ->collapsible(),
            FormField::addRow(breakpointName: 'md' ),
            AssociationField::new('vendor', 'Prüfunternehmen')
                ->setColumns(8),
            FormField::addRow(breakpointName: 'md' ),
            TextEditorField::new('notes', 'Bemerkungen')
                ->setColumns(5),
            FormField::addFieldset('Angebotsinformationen')
                ->collapsible(),
            FormField::addRow(breakpointName: 'md' ),
            TextEditorField::new('costInformation')
                ->setColumns(5),
            FormField::addFieldset('Flascheninformationen')
                ->collapsible(),
            FormField::addRow(breakpointName: 'md' ),
            AssociationField::new('tanks', 'Flaschen')
                ->setColumns(8),
			DateField::new('registrationDeadline', 'Anmeldeschluss')
				->setRequired(false),
            // TankCheckArticle fields as a collection
            CollectionField::new('articles')
                //->setEntryType(TankCheckArticleType::class)
                ->useEntryCrudForm(TankCheckArticleCrudController::class)
                ->setEntryIsComplex()
                ->setFormTypeOption('by_reference', false)
                ->onlyOnForms()
                ->setLabel('Artikel')
                ->renderExpanded()
            ->setColumns(12), // Hier die Breite auf 12 Spalten setzen
            ArrayField::new('articles')->hideOnForm()->setLabel('Artikel')
        ];
    }

    public function createEntity(string $entityFqcn)
    {
        $tankCheck = new TankCheck();
        $tankCheck->setCheckDate(new \DateTime('now'));
        return $tankCheck;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Prüfungstermin')
            ->setEntityLabelInPlural('Prüfungstermine')
            ->setSearchFields(['vendorName', 'notes', 'costInformation'])
            ->setDefaultSort(['checkDate' => 'DESC']);
    }
}
