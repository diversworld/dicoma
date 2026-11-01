<?php
declare(strict_types=1);
namespace App\Controller\Admin;
use App\Service\AdminClubContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_SUPER_ADMIN')]
final class TuvInvoiceSettingsController extends AbstractController
{
    #[Route('/admin/tuv/invoice/settings/{id}', name: 'admin_tuv_invoice_settings', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request, AdminClubContext $clubContext): Response
    {
        if ($clubContext->getClub()?->getId() !== $id) {
            throw $this->createAccessDeniedException('Verein entspricht nicht der aktuellen Auswahl.');
        }
        if (!$request->isMethod('GET')) {
            return new Response('Bitte das aktuelle Verwaltungsformular verwenden.', Response::HTTP_GONE);
        }
        return $this->redirectToRoute('admin_invoice_settings_edit', ['id' => $id], Response::HTTP_SEE_OTHER);
    }
}
