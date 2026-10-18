<?php
declare(strict_types=1);
namespace App\Controller\Admin;
use App\Entity\TuvNotification;
use App\Service\TuvNotificationSender;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class TuvNotificationSendNowController extends AbstractController
{
    #[Route('/admin/tuv-notification/{id}/send-now', name: 'admin_tuv_notification_send_now', methods: ['POST'], requirements: ['id' => '\\d+'])]
    public function sendNow(int $id, Request $request, EntityManagerInterface $em, TuvNotificationSender $sender, AdminUrlGenerator $urls): Response
    {
        if (!$this->isCsrfTokenValid('tuv-notification-send-'.$id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Ungültiges CSRF-Token.');
        }
        $item = $em->find(TuvNotification::class, $id);
        if (!$item || $item->getState() !== 'pending') {
            $this->addFlash('warning', 'Nachricht ist nicht mehr ausstehend.');
        } else {
            $result = $sender->sendOne($id);
            $this->addFlash($result === 'sent' ? 'success' : 'warning', match ($result) {
                'sent' => 'Nachricht wurde versendet.',
                'failed' => 'Versand fehlgeschlagen. Details im Postausgang.',
                'locked' => 'Ein anderer Versand läuft. Bitte später erneut versuchen.',
                default => 'Nachricht wurde inzwischen verarbeitet.',
            });
        }
        return $this->redirect($urls->setController(TuvNotificationCrudController::class)->setAction(Action::DETAIL)->setEntityId($id)->generateUrl());
    }
}
