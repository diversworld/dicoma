<?php
declare(strict_types=1);
namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Legacy links: collection is performed only by the equipment manager. */
#[IsGranted('ROLE_SUPER_ADMIN')]
final class TuvPaymentIntakeController extends AbstractController
{
    #[Route('/admin/tuv/check/{id}/payment-intake', name: 'admin_tuv_payment_intake', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function index(int $id): Response
    {
        if ($this->isGranted('ROLE_EQUIPMENT_MANAGER')) {
            return $this->redirectToRoute('equipment_manager_cashbook');
        }
        $this->addFlash('warning', 'Die Geldannahme erfolgt ausschließlich im Gerätewartbereich. Ein Gerätewart-Zugang ist erforderlich.');
        return $this->redirectToRoute('admin');
    }

    #[Route('/admin/tuv/booking/{id}/collect-payment', name: 'admin_tuv_collect_payment', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function collect(int $id): Response
    {
        // The legacy admin POST must never create a second payment entry point.
        throw $this->createAccessDeniedException('Die Zahlungsannahme wurde in die Gerätewart-Kasse verlegt.');
    }
}
