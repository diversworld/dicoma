<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\MemberTankCheckBooking;
use App\Entity\MemberTankCheckResult;
use App\Service\TuvNextInspectionDateCalculator;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class TuvInspectionResultSaveController extends AbstractController
{
    #[Route('/admin/tuv/inspection-result/{id}/save', name: 'admin_tuv_inspection_result_save', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function save(int $id, Request $request, EntityManagerInterface $em, AdminUrlGenerator $urls, TuvNextInspectionDateCalculator $calculator): RedirectResponse
    {
        $booking = $em->getRepository(MemberTankCheckBooking::class)->find($id);
        if (!$booking instanceof MemberTankCheckBooking) {
            throw $this->createNotFoundException('TÜV-Anmeldung nicht gefunden.');
        }
        if (!$this->isCsrfTokenValid('tuv_inspection_result_'.$id, (string) $request->request->get('_token', ''))) {
            throw $this->createAccessDeniedException('Ungültiger CSRF-Token.');
        }
        $backUrl = $urls->unsetAll()
            ->setController(MemberTankCheckBookingCrudController::class)
            ->setAction('inspectionResult')
            ->setEntityId($id)
            ->generateUrl();
        if (!in_array($booking->getStatus(), ['returned', 'completed'], true)) {
            $this->addFlash('danger', 'Ein Prüfergebnis ist erst nach Rückerhalt möglich.');
            return $this->redirect($backUrl);
        }

        $choice = (string) $request->request->get('result', '');
        $dateRaw = (string) $request->request->get('inspected_at', '');
        $notes = trim((string) $request->request->get('notes', ''));
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $dateRaw);
        if (!in_array($choice, ['passed', 'failed'], true)
            || $date === false || $date->format('Y-m-d') !== $dateRaw
            || $date > new \DateTimeImmutable('today') || mb_strlen($notes) > 5000) {
            $this->addFlash('danger', 'Ungültige Eingabe: Ergebnis, Prüfdatum und Bemerkung prüfen.');
            return $this->redirect($backUrl);
        }

        $result = $em->getRepository(MemberTankCheckResult::class)->findOneBy(['booking' => $booking]);
        $result ??= new MemberTankCheckResult($booking);
        $actor = $this->getUser()?->getUserIdentifier() ?? 'Unbekannt';
        $result->record($choice, $date, $notes !== '' ? $notes : null, $actor);
        // Bei bestandener Prüfung gilt der tatsächliche Prüftag + 30 Kalendermonate.
        // Ein fehlgeschlagenes Ergebnis überschreibt den bestehenden Termin nicht.
        if ($choice === 'passed') {
            $tank = $booking->getMemberTank();
            $nextDate = $calculator->calculate($date);
            if ($booking->getClubTank() !== null) {
                $clubTank = $booking->getClubTank();
                $last = $clubTank->getLastCheckDate();
                if ($last === null || $date->format('Y-m-d') >= $last->format('Y-m-d')) {
                    $clubTank->setLastCheckDate(\DateTime::createFromImmutable($date));
                    $clubTank->setNextCheckDate(\DateTime::createFromImmutable($nextDate));
                }
            } elseif ($tank === null) {
                throw $this->createNotFoundException('Die Flasche dieser Anmeldung existiert nicht mehr.');
            } else {
            // Korrekturen älterer Prüfungen dürfen einen bereits später gesetzten
            // Termin nicht versehentlich zurückdatieren.
            $existing = $tank->getNextInspectionAt();
            if ($existing === null || $nextDate > $existing) {
                $tank->setNextInspectionAt($nextDate);
            }
            }
        }
        $em->persist($result);
        $em->flush();
        $this->addFlash('success', 'TÜV-Prüfergebnis gespeichert.');
        return $this->redirect($backUrl);
    }
}
