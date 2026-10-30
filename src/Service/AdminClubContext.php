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
    ) {}

    public function getClub(): ?Club
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return null;
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

    public function selectionForm(): FormInterface
    {
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
        $user = $this->security->getUser();
        if (!$user instanceof User || !$this->security->isGranted('ROLE_SUPER_ADMIN') || $club->getId() === null) {
            throw new \LogicException('Eine gültige Superadmin-Vereinsauswahl ist erforderlich.');
        }
        $this->requests->getSession()->set($this->sessionKey($user), $club->getId());
    }

    private function sessionKey(User $user): string
    {
        return 'admin_club_'.$user->getId();
    }
}
