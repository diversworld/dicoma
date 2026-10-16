<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\MemberTankCheckBooking;
use App\Entity\MemberTankCheckStatusHistory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;

#[IsGranted('ROLE_ADMIN')]
final class TuvBookingOverviewController extends AbstractController
{
    private const NEXT_STATUSES = [
        'requested' => ['received'],
        'received' => ['sent'],
        'sent' => ['returned'],
        'returned' => ['completed'],
    ];

    #[Route('/admin/tuv/bookings/{id}/status', name: 'admin_tuv_booking_status', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function changeStatus(int $id, Request $request, EntityManagerInterface $em, AdminUrlGenerator $adminUrlGenerator): Response
    {
        $booking = $em->getRepository(MemberTankCheckBooking::class)->find($id);
        if ($booking === null) {
            throw $this->createNotFoundException('TÜV-Anmeldung nicht gefunden.');
        }
        if (!$this->isCsrfTokenValid('admin_tuv_booking_status_'.$id, (string) $request->request->get('_token', ''))) {
            throw $this->createAccessDeniedException('Ungültiger CSRF-Token.');
        }
        $target = $request->request->get('status', '');
        if (!is_string($target) || !in_array($target, self::NEXT_STATUSES[$booking->getStatus()] ?? [], true)) {
            $this->addFlash('danger', 'Dieser Statuswechsel ist nicht erlaubt.');
        } else {
            $oldStatus = $booking->getStatus();
            $actor = $this->getUser()?->getUserIdentifier() ?? 'unbekannt';
            $em->persist(new MemberTankCheckStatusHistory($booking, $oldStatus, $target, $actor));
            $booking->setStatus($target);
            $em->flush();
            $this->addFlash('success', 'Bearbeitungsstatus aktualisiert.');
        }
        return $this->redirect($adminUrlGenerator->unsetAll()->setController(MemberTankCheckBookingCrudController::class)->setAction(Action::INDEX)->generateUrl(), Response::HTTP_SEE_OTHER);
    }

    #[Route('/admin/tuv/bookings', name: 'admin_tuv_booking_overview', methods: ['GET'])]
    public function index(AdminUrlGenerator $adminUrlGenerator): Response
    {
        return $this->redirect(
            $adminUrlGenerator->unsetAll()
                ->setController(MemberTankCheckBookingCrudController::class)
                ->setAction(Action::INDEX)
                ->generateUrl(),
            Response::HTTP_SEE_OTHER
        );
    }
}
