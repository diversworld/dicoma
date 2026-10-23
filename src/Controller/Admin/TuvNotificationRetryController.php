<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\TuvNotification;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_SUPER_ADMIN')]
final class TuvNotificationRetryController extends AbstractController
{
    #[Route(
        '/admin/tuv-notification/{id}/retry',
        name: 'admin_tuv_notification_retry_post',
        methods: ['POST'],
        requirements: ['id' => '\d+']
    )]
    public function retry(
        int $id,
        Request $request,
        EntityManagerInterface $em,
        AdminUrlGenerator $urls
    ): Response {
        if (!$this->isCsrfTokenValid(
            'tuv-notification-retry-'.$id,
            (string) $request->request->get('_token', '')
        )) {
            throw $this->createAccessDeniedException('Ungültiges CSRF-Token.');
        }

        $notification = $em->find(TuvNotification::class, $id);

        if (!$notification instanceof TuvNotification) {
            throw $this->createNotFoundException('Nachricht nicht gefunden.');
        }

        if ($notification->getState() !== 'failed') {
            $this->addFlash(
                'warning',
                'Nur fehlgeschlagene Nachrichten können erneut vorgemerkt werden.'
            );
        } else {
            $notification->resetForRetry();
            $em->flush();

            $this->addFlash(
                'success',
                'Nachricht wurde erneut zum Versand vorgemerkt.'
            );
        }

        return $this->redirect(
            $urls
                ->setController(TuvNotificationCrudController::class)
                ->setAction(Action::DETAIL)
                ->setEntityId($id)
                ->generateUrl()
        );
    }
}
