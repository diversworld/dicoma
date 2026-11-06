<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Club;
use App\Entity\User;
use App\Form\ConfigurationClubSelectionType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class AdminClubContext
{
    public function __construct(
        private readonly Security $security,
        private readonly RequestStack $requests,
        private readonly EntityManagerInterface $em,
        private readonly FormFactoryInterface $forms,
        private readonly UrlGeneratorInterface $urls,
        private readonly SingleClubMode $singleClub,
    ) {}

    public function getClub(): ?Club
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return null;
        }
        if ($this->singleClub->isEnabled()) {
            if (!$this->singleClub->permitsUser($user)) {
                return null;
            }
            $club = $this->singleClub->club();
            if (!$this->security->isGranted('ROLE_SUPER_ADMIN')
                && $user->getMember()?->getClub()?->getId() !== $club->getId()) {
                return null;
            }
            return $club;
        }
        $request = $this->requests->getCurrentRequest();
        if ($this->security->isGranted('ROLE_SUPER_ADMIN') && $request !== null && $request->hasSession()) {
            $id = $request->getSession()->get($this->sessionKey($user));
            if ($id !== null) {
                return $this->em->find(Club::class, $id);
            }
        }

        return $user->getMember()?->getClub();
    }

    public function isBackendRequest(): bool
    {
        $path = $this->requests->getCurrentRequest()?->getPathInfo();
        return $path !== null && ($path === '/admin' || str_starts_with($path, '/admin/'));
    }

    public function selectionForm(): FormInterface
    {
        if ($this->singleClub->isEnabled()) {
            throw new \LogicException('Im Einzelvereinsbetrieb ist keine Vereinsauswahl verfügbar.');
        }
        return $this->forms->createNamed('admin_club', ConfigurationClubSelectionType::class, ['club' => $this->getClub()], [
            'clubs' => $this->em->getRepository(Club::class)->findBy([], ['name' => 'ASC']),
            'club_label' => 'Verein',
            'include_year' => false,
            'method' => 'POST',
            'csrf_protection' => true,
            'action' => $this->urls->generate('admin_club_select'),
        ]);
    }

    public function select(Club $club): void
    {
        if ($this->singleClub->isEnabled()) {
            throw new \LogicException('Im Einzelvereinsbetrieb ist kein Vereinswechsel möglich.');
        }
        $user = $this->security->getUser();
        if (!$user instanceof User || !$this->security->isGranted('ROLE_SUPER_ADMIN') || $club->getId() === null) {
            throw new \LogicException('Eine gültige Superadmin-Vereinsauswahl ist erforderlich.');
        }
        $this->requests->getSession()->set($this->sessionKey($user), $club->getId());
    }

    public function isSingleClubMode(): bool
    {
        return $this->singleClub->isEnabled();
    }

    private function sessionKey(User $user): string
    {
        return 'admin_club_'.$user->getId();
    }
}
