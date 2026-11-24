<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Controller\Admin\DashboardController;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class AdminNavigationContextSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['ensureEasyAdminContext', 1000]];
    }

    public function ensureEasyAdminContext(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $request = $event->getRequest();
        $path = $request->getPathInfo();
        if (!str_starts_with($path, '/admin') || $path === '/admin') {
            return;
        }
        $request->attributes->set('routeCreatedByEasyAdmin', true);
        $request->attributes->set('dashboardControllerFqcn', DashboardController::class);
    }
}
