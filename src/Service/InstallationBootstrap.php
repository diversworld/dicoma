<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Club;
use App\Entity\Member;
use App\Entity\Person;
use App\Entity\User;
use App\Security\BootstrapRegistrationLock;
use App\Security\InstallationGuard;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class InstallationBootstrap
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly InstallationCatalog $catalog,
        private readonly InstallationGuard $guard,
        private readonly BootstrapRegistrationLock $lock,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly InstallationEnvironment $environment,
    ) {}

    /** @return array{club:Club,user:User,installed:list<string>} */
    public function install(array $data): array
    {
        if ($this->em->getRepository(Club::class)->count([]) > 0 || $this->em->getRepository(User::class)->count([]) > 0) {
            throw new \DomainException('Erstinstallation ist nur für eine leere Datenbank zulässig.');
        }
        return $this->lock->synchronized(function () use ($data): array {
            $this->guard->denyUnlessExplicitlyEnabled();
            if ($this->em->getRepository(Club::class)->count([]) > 0 || $this->em->getRepository(User::class)->count([]) > 0) {
                throw new \DomainException('Installation wurde zwischenzeitlich bereits begonnen.');
            }
            $result = $this->em->wrapInTransaction(function () use ($data): array {
                $club = (new Club())
                    ->setName(trim((string) $data['clubName']))
                    ->setShortName(trim((string) ($data['clubShortName'] ?? '')) ?: null)
                    ->setEmail(trim((string) $data['clubEmail']))
                    ->setCountry('DE')
                    ->setActive(true);
                $person = (new Person())
                    ->setFirstname(trim((string) $data['firstname']))
                    ->setLastname(trim((string) $data['lastname']))
                    ->setEmail(trim((string) $data['email']));
                $member = (new Member())->setClub($club)->setPerson($person)->setMemberNumber('0001')->setStatus('active');
                $user = (new User())->setUser(trim((string) $data['username']))
                    ->setEmail(trim((string) $data['email']))
                    ->setIsVerified(true)
                    ->setRoles([User::ROLE_SUPER_ADMIN]);
                $user->setPassword($this->passwordHasher->hashPassword($user, (string) $data['password']));
                $member->setUser($user);
                $this->em->persist($club);
                $this->em->persist($user);
                $this->em->flush();
                $installed = $this->catalog->installMissing($club);
                $this->em->flush();
                return ['club' => $club, 'user' => $user, 'installed' => $installed];
            });
            $this->environment->complete((int) $result['club']->getId(), (string) $result['club']->getEmail());
            $this->guard->markCompleted();
            return $result;
        });
    }
}
