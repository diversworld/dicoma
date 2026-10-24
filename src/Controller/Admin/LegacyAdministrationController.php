<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class LegacyAdministrationController extends AbstractController
{
    public function __invoke(Request $request, AdminUrlGenerator $urls): Response
    {
        $this->denyAccessUnlessGranted($request->attributes->getString('legacy_permission'));
        if ($request->attributes->has('legacy_target')) {
            $target = $this->generateUrl($request->attributes->getString('legacy_target'));
        } elseif (!$this->isGranted($request->attributes->getString('legacy_canonical_permission'))) {
            $target = $this->generateUrl($request->attributes->getString('legacy_fallback'));
        } else {
            $generator = (clone $urls)->unsetAll()->setDashboard(DashboardController::class)
                ->setController($request->attributes->getString('legacy_crud'))
                ->setAction($request->attributes->getString('legacy_action'));
            if (in_array($request->attributes->getString('legacy_action'), [Action::DETAIL, Action::EDIT], true)) {
                $generator->setEntityId($request->attributes->getInt('id'));
            }
            $target = $generator->generateUrl();
        }
        if ($request->isMethodSafe()) {
            return $this->redirect($target, Response::HTTP_SEE_OTHER);
        }
        return $this->render('admin/legacy_retired.html.twig', ['targetUrl' => $target],
            new Response(status: Response::HTTP_GONE));
    }
}
