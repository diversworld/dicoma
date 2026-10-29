<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\WebsiteDisplaySettings;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class WebsitePagination
{
    public function __construct(private readonly EntityManagerInterface $em, private readonly PublicWebsite $website) {}

    /** @return array{articles: int, events: int} */
    public function limits(): array
    {
        $club = $this->website->club();
        $settings = $club === null ? null : $this->em->find(WebsiteDisplaySettings::class, $club->getId());
        return ['articles' => $settings?->getArticlesPerPage() ?? 6, 'events' => $settings?->getEventsPerPage() ?? 6];
    }

    /**
     * @template T
     * @param list<T> $items
     * @return array{items: list<T>, page: int, pages: int}
     */
    public function page(array $items, int $limit, Request $request, string $parameter): array
    {
        if ($limit < 1 || $limit > 100) {
            throw new \LogicException('Ungültige Seitengröße in den Website-Einstellungen.');
        }
        $value = $request->query->all()[$parameter] ?? '1';
        if (!is_string($value) || !ctype_digit($value) || ($page = filter_var($value, FILTER_VALIDATE_INT)) === false || $page < 1) {
            throw new BadRequestHttpException('Ungültige Seitenzahl.');
        }
        $pages = max(1, (int) ceil(count($items) / $limit));
        $page = min($page, $pages);
        return ['items' => array_slice($items, ($page - 1) * $limit, $limit), 'page' => $page, 'pages' => $pages];
    }
}
