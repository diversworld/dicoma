<?php

declare(strict_types=1);
namespace App\Controller\Admin;

use App\Entity\Tank;
use App\Entity\TankInspectionResult;
use App\Service\TuvNextInspectionDateCalculator;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_SUPER_ADMIN')]
final class ClubTankInspectionController extends AbstractController
{
    #[Route('/admin/tuv/club-tank/{id}', name: 'admin_tuv_club_tank_inspection', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    public function index(int $id, Request $request, EntityManagerInterface $em, AdminUrlGenerator $urls, TuvNextInspectionDateCalculator $calculator): Response
    {
        $easyAdminUrl = (clone $urls)->unsetAll()->setController(TankCrudController::class)
            ->setAction('clubInspection')->setEntityId($id)->generateUrl();
        if ($request->isMethod('GET')) {
            return $this->redirect($easyAdminUrl, Response::HTTP_SEE_OTHER);
        }
        $tank = $em->getRepository(Tank::class)->find($id);
        if (!$tank instanceof Tank) { throw $this->createNotFoundException('Vereinsflasche nicht gefunden.'); }
        $backUrl = $urls->unsetAll()->setController(TankCrudController::class)->setAction(Action::INDEX)->generateUrl();
        $history = $em->getRepository(TankInspectionResult::class)->findBy(['tank' => $tank], ['createdAt' => 'DESC', 'id' => 'DESC']);
        $errors = [];
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('club_tuv_'.$id, (string) $request->request->get('_token', ''))) {
                throw $this->createAccessDeniedException('Ungültiger CSRF-Token.');
            }
            $choice = (string) $request->request->get('result', '');
            $raw = (string) $request->request->get('inspected_at', '');
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $raw);
            $notes = trim((string) $request->request->get('notes', ''));
            if (!in_array($choice, ['passed', 'failed'], true)) { $errors[] = 'Ungültiges Prüfergebnis.'; }
            if ($date === false || $date->format('Y-m-d') !== $raw || $date > new \DateTimeImmutable('today')) { $errors[] = 'Ungültiges Prüfdatum.'; }
            if (mb_strlen($notes) > 5000) { $errors[] = 'Bemerkung ist zu lang.'; }
            if ($errors === []) {
                // Eine ältere Prüfung darf keine neuere Prüfung überschreiben.
                $previous = $tank->getLastCheckDate();
                if ($choice === 'passed' && $previous !== null && $date < \DateTimeImmutable::createFromInterface($previous)->setTime(0, 0)) {
                    $errors[] = 'Das Prüfdatum liegt vor der zuletzt dokumentierten Prüfung.';
                } else {
                    $record = new TankInspectionResult($tank, $choice, $date, $notes !== '' ? $notes : null, $this->getUser()?->getUserIdentifier() ?? 'Unbekannt');
                    $em->persist($record);
                    if ($choice === 'passed') {
                        $next = $calculator->calculate($date);
                        $existing = $tank->getNextCheckDate();
                        $tank->setLastCheckDate(\DateTime::createFromImmutable($date));
                        if ($existing === null || $next > \DateTimeImmutable::createFromInterface($existing)) {
                            $tank->setNextCheckDate(\DateTime::createFromImmutable($next));
                        }
                    }
                    $em->flush();
                    $this->addFlash('success', 'Prüfergebnis gespeichert.');
                    return $this->redirect($easyAdminUrl, Response::HTTP_SEE_OTHER);
                }
            }
        }
        return $this->render('admin/tuv/club_tank_inspection.html.twig', [
            'tank' => $tank, 'history' => $history, 'errors' => $errors, 'backUrl' => $backUrl,
        ]);
    }
}
