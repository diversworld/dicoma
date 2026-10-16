<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'member_tank_check_booking_item')]
#[ORM\Index(
    name: 'idx_booking_item_booking',
    columns: ['booking_id']
)]
#[ORM\Index(
    name: 'idx_booking_item_article',
    columns: ['article_id']
)]
#[ORM\UniqueConstraint(name: 'uniq_booking_article', columns: ['booking_id', 'article_id'])]
class MemberTankCheckBookingItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: MemberTankCheckBooking::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?MemberTankCheckBooking $booking = null;

    #[ORM\ManyToOne(targetEntity: TankCheckArticle::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private ?TankCheckArticle $article = null;

    #[ORM\Column(length: 255)]
    private string $titleSnapshot = '';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $netSnapshot = '0.00';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $grossSnapshot = '0.00';

    #[ORM\Column(type: 'integer')]
    private int $quantity = 1;

    public function getId(): ?int { return $this->id; }
    public function getBooking(): ?MemberTankCheckBooking { return $this->booking; }
    public function getArticle(): ?TankCheckArticle { return $this->article; }
    public function getTitleSnapshot(): string { return $this->titleSnapshot; }
    public function getNetSnapshot(): string { return $this->netSnapshot; }
    public function getGrossSnapshot(): string { return $this->grossSnapshot; }
    public function getQuantity(): int { return $this->quantity; }

    public function setBooking(MemberTankCheckBooking $booking): self { $this->booking = $booking; return $this; }
    public function setArticle(TankCheckArticle $article): self { $this->article = $article; return $this; }
    public function setTitleSnapshot(string $title): self { $this->titleSnapshot = $title; return $this; }
    public function setNetSnapshot(string $net): self { $this->netSnapshot = $net; return $this; }
    public function setGrossSnapshot(string $gross): self { $this->grossSnapshot = $gross; return $this; }
}
