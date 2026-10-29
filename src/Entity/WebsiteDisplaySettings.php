<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
class WebsiteDisplaySettings
{
    #[ORM\Id, ORM\OneToOne(targetEntity: Club::class)]
    #[ORM\JoinColumn(name: 'club_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Club $club;

    #[ORM\Column, Assert\Range(min: 1, max: 100)]
    private int $articlesPerPage = 6;

    #[ORM\Column, Assert\Range(min: 1, max: 100)]
    private int $eventsPerPage = 6;

    public function __construct(Club $club) { $this->club = $club; }
    public function getArticlesPerPage(): int { return $this->articlesPerPage; }
    public function setArticlesPerPage(int $value): void { $this->articlesPerPage = $value; }
    public function getEventsPerPage(): int { return $this->eventsPerPage; }
    public function setEventsPerPage(int $value): void { $this->eventsPerPage = $value; }
}
