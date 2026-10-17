<?php
declare(strict_types=1);
namespace App\Controller\Admin;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request,Response};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
#[IsGranted('ROLE_ADMIN')]
final class TuvMessageTemplateController extends AbstractController
{
    #[Route('/admin/tuv/message-templates', name: 'admin_tuv_message_templates', methods: ['GET'])]
    public function index(Request $request): Response
    {
        return $this->redirectToRoute('admin_settings_tuv_notifications', ['kind' => $request->query->get('kind', 'confirmation')]);
    }
}
