<?php
declare(strict_types=1);
namespace App\Controller\Admin;
use App\Entity\{Club, TuvInvoiceSettings};
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_SUPER_ADMIN')]
final class TuvInvoiceSettingsController extends AbstractController
{
    #[Route('/admin/tuv/invoice/settings/{id}', name: 'admin_tuv_invoice_settings', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request, EntityManagerInterface $em): Response
    {
        $club = $em->getRepository(Club::class)->find($id);
        if (!$club) { throw $this->createNotFoundException(); }
        $settings = $em->getRepository(TuvInvoiceSettings::class)->find($id);
        if (!$settings) { $settings = new TuvInvoiceSettings($club); }
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('invoice_settings_'.$id, (string)$request->request->get('_token', ''))) {
                throw $this->createAccessDeniedException('Ungültiges CSRF-Token.');
            }
            try {
                $settings->configure(
                    (string)$request->request->get('tax_mode', ''),
                    (string)$request->request->get('tax_number', ''),
                    (string)$request->request->get('vat_id', ''),
                    (string)$request->request->get('invoice_note', '')
                );
                $settings->setMemberAgencyEnabled($request->request->get('member_agency_enabled') === '1');
                $em->persist($settings);
                $em->flush();
                $this->addFlash('success', 'Rechnungseinstellungen gespeichert.');
                return $this->redirectToRoute('admin_tuv_invoice_settings', ['id' => $id]);
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('danger', $e->getMessage());
            }
        }
        return $this->render('admin/tuv_invoice_settings.html.twig', ['club' => $club, 'settings' => $settings]);
    }
}
