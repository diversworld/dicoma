<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\{Club, Sport, User, WebsitePublication};
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

    public function publicClub(): ?Club
    {
        $club = $this->club();
        return $club?->isActive() ? $club : null;
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
        if ($publication->getClub()->getId() !== $this->publicClub()?->getId() || !$publication->isPublished() || $publication->getPublishAt() === null || $publication->getPublishAt() > new \DateTimeImmutable()) { return false; }
        $division = $publication->getEffectiveDivision();
        if ($division !== null && (!$division->isActive() || $division->getClub()?->getId() !== $publication->getClub()->getId())) { return false; }
        foreach ([$publication->getCourse(), $publication->getTournament()] as $source) {
            if ($source !== null && ($source->getClub()?->getId() !== $publication->getClub()->getId() || in_array($source->getStatus()->value, ['draft', 'cancelled'], true))) { return false; }
        }
        return true;
    }

    public function publications(string $kind, bool $upcoming = false): array
    {
        $club = $this->publicClub();
        if ($club === null) { return []; }
        $qb = $this->em->getRepository(WebsitePublication::class)->createQueryBuilder('p')
            ->leftJoin('p.course', 'c')->addSelect('c')->leftJoin('p.tournament', 't')->addSelect('t')
            ->leftJoin('p.division', 'd')->addSelect('d')
            ->leftJoin('p.parent', 'parent')->addSelect('parent')
            ->where('p.club = :club AND p.kind = :kind AND p.published = true AND p.publishAt <= :now')
            ->setParameter('club', $club)->setParameter('kind', $kind)->setParameter('now', new \DateTimeImmutable());
        if ($upcoming) { $qb->andWhere('p.endsAt >= :now'); }
        $qb->orderBy($kind === 'page' ? 'p.sortOrder' : ($kind === 'event' ? 'p.startsAt' : 'p.publishAt'), $kind === 'article' ? 'DESC' : 'ASC')->addOrderBy('p.id', $kind === 'article' ? 'DESC' : 'ASC');
        return array_values(array_filter($qb->getQuery()->getResult(), $this->visible(...)));
    }

    /** @return list<WebsitePublication> */
    public function articlesForPage(WebsitePublication $page): array
    {
        if ($page->getKind() !== 'page') {
            return [];
        }
        return array_values(array_filter(
            $this->em->getRepository(WebsitePublication::class)->findBy(
                ['club' => $page->getClub(), 'kind' => 'article', 'parent' => $page],
                ['publishAt' => 'DESC', 'sortOrder' => 'ASC', 'id' => 'DESC'],
            ),
            $this->visible(...),
        ));
    }

    /** @return array<int, array{division: Sport, publications: list<WebsitePublication>}> */
    public function divisions(): array
    {
        $divisions = [];
        foreach (['page', 'article', 'event'] as $kind) {
            foreach ($this->publications($kind) as $publication) {
                $division = $publication->getEffectiveDivision();
                if ($division === null) { continue; }
                $id = $division->getId();
                $divisions[$id] ??= ['division' => $division, 'publications' => []];
                $divisions[$id]['publications'][] = $publication;
            }
        }
        uasort($divisions, static fn (array $a, array $b): int =>
            [$a['division']->getSortOrder(), $a['division']->getName(), $a['division']->getId()]
            <=> [$b['division']->getSortOrder(), $b['division']->getName(), $b['division']->getId()]
        );
        return $divisions;
    }

    /** @return list<array{publication: ?WebsitePublication, event: ?\App\Entity\ClubEvent, startsAt: \DateTimeImmutable}> */
    public function upcomingEvents(ClubEventBooking $booking, ?User $user): array
    {
        $entries = [];
        foreach ($this->publications('event', true) as $publication) {
            $entries[] = ['publication' => $publication, 'event' => null, 'startsAt' => $publication->getStartsAt()];
        }
        $club = $this->publicClub();
        if ($club !== null) {
            foreach ($booking->visibleUpcoming($club, $user) as $event) {
                $entries[] = ['publication' => null, 'event' => $event, 'startsAt' => $event->getStartsAt()];
            }
        }
        usort($entries, static fn (array $a, array $b): int => $a['startsAt'] <=> $b['startsAt']);
        return $entries;
    }
}
