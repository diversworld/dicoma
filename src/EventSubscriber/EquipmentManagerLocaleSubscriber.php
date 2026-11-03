<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class EquipmentManagerLocaleSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['applyLocale', 20]];
    }

    public function applyLocale(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $request = $event->getRequest();
        if (!$request->hasSession()) {
            return;
        }
        $locales = ['de', 'en', 'fr', 'es'];
        $locale = $request->attributes->get('_locale');
        if (is_string($locale) && in_array($locale, $locales, true)) {
            $request->getSession()->set('equipment_manager_locale', $locale);
        } elseif ($request->hasPreviousSession()) {
            $saved = $request->getSession()->get('equipment_manager_locale');
            if (is_string($saved) && in_array($saved, $locales, true)) {
                $request->attributes->set('_locale', $saved);
            }
        }
    }
}
