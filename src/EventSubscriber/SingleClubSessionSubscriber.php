<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\User;
use App\Service\SingleClubMode;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

final class SingleClubSessionSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly SingleClubMode $singleClub,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['checkMembership', 0]];
    }

    public function checkMembership(RequestEvent $event): void
    {
        if (!$event->isMainRequest() || $event->getRequest()->getPathInfo() === '/logout') {
            return;
        }
        $user = $this->security->getUser();
        if ($user instanceof User && !$this->singleClub->permitsUser($user)) {
            throw new AccessDeniedHttpException('Das Konto ist nicht dem konfigurierten Verein zugeordnet.');
        }
    }
}
