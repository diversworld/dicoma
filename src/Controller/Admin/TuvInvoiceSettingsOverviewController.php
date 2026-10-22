<?php
declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Club;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_SUPER_ADMIN')]
final class TuvInvoiceSettingsOverviewController extends AbstractController
{
    #[Route('/admin/tuv/invoice/settings', name: 'admin_tuv_invoice_settings_overview', methods: ['GET'])]
    public function index(EntityManagerInterface $em): Response
    {
        $clubs = $em->getRepository(Club::class)->findBy([], ['name' => 'ASC']);
        return $this->render('admin/tuv_invoice_settings_overview.html.twig', ['clubs' => $clubs]);
    }
}
