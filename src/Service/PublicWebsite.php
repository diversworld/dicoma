<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\{Club, User, WebsitePublication};
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class PublicWebsite
{
    public function __construct(private readonly EntityManagerInterface $em, private readonly Security $security, private readonly int $clubId) {}

    public function club(): ?Club
    {
        if ($this->clubId === 0) { return null; }
        $club = $this->em->find(Club::class, $this->clubId);
        if ($club === null) { throw new \LogicException('PUBLIC_SITE_CLUB_ID refers to a missing club.'); }
        return $club;
    }

    public function editorialClub(): Club
    {
        $club = $this->club();
        if ($club === null || !$this->canManageEditorial()) {
            throw new AccessDeniedException('Redaktion nur für Vorstand/Pressewart des konfigurierten Website-Vereins. PUBLIC_SITE_CLUB_ID prüfen.');
        }

        return $club;
    }

    public function canManageEditorial(): bool
    {
        $user = $this->security->getUser();
        $club = $this->club();

        return $this->security->isGranted(User::ROLE_PRESS_OFFICER)
            && $user instanceof User
            && $club !== null
            && $user->getMember()?->getClub()?->getId() === $club->getId();
    }

    public function visible(WebsitePublication $publication): bool
    {
        if ($publication->getClub()->getId() !== $this->club()?->getId() || !$publication->isPublished() || $publication->getPublishAt() === null || $publication->getPublishAt() > new \DateTimeImmutable()) { return false; }
        foreach ([$publication->getCourse(), $publication->getTournament()] as $source) {
            if ($source !== null && ($source->getClub()?->getId() !== $publication->getClub()->getId() || in_array($source->getStatus()->value, ['draft', 'cancelled'], true))) { return false; }
        }
        return true;
    }

    public function publications(string $kind, bool $upcoming = false): array
    {
        $club = $this->club();
        if ($club === null) { return []; }
        $qb = $this->em->getRepository(WebsitePublication::class)->createQueryBuilder('p')
            ->leftJoin('p.course', 'c')->addSelect('c')->leftJoin('p.tournament', 't')->addSelect('t')
            ->where('p.club = :club AND p.kind = :kind AND p.published = true AND p.publishAt <= :now')
            ->setParameter('club', $club)->setParameter('kind', $kind)->setParameter('now', new \DateTimeImmutable());
        if ($upcoming) { $qb->andWhere('p.endsAt >= :now'); }
        $qb->orderBy($kind === 'event' ? 'p.startsAt' : 'p.publishAt', $kind === 'event' ? 'ASC' : 'DESC')->addOrderBy('p.id', 'DESC');
        return array_values(array_filter($qb->getQuery()->getResult(), $this->visible(...)));
    }
}
