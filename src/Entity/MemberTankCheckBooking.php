<?php

declare(strict_types=1);
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'member_tank_check_booking')]
#[ORM\Index(name: 'idx_book_member', columns: ['member_id'])]
#[ORM\Index(name: 'idx_book_tank', columns: ['member_tank_id'])]
#[ORM\Index(name: 'idx_book_check', columns: ['tank_check_id'])]
#[ORM\UniqueConstraint(name: 'uniq_member_tank_check', columns: ['member_tank_id', 'tank_check_id'])]
#[ORM\UniqueConstraint(name: 'UNIQ_TUV_CLUB_TANK_CHECK', columns: ['club_tank_id', 'tank_check_id'])]
class MemberTankCheckBooking
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\ManyToOne(targetEntity: Member::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'RESTRICT')]
    private ?Member $member = null;
    #[ORM\ManyToOne(targetEntity: MemberTank::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'RESTRICT')]
    private ?MemberTank $memberTank = null;
    #[ORM\ManyToOne(targetEntity: Tank::class)]
    #[ORM\JoinColumn(name: 'club_tank_id', nullable: true, onDelete: 'RESTRICT')]
    private ?Tank $clubTank = null;
    #[ORM\ManyToOne(targetEntity: TankCheck::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private ?TankCheck $tankCheck = null;
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $articleTitleSnapshot = null;
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $priceSnapshot = null;
    #[ORM\Column(length: 10, nullable: true)]
    private ?string $priceBasis = null;
    #[ORM\Column(length: 20, options: ['default' => 'requested'])]
    private string $status = 'requested';
    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;
    public function __construct() { $this->createdAt = new \DateTimeImmutable(); }
    public function getId(): ?int { return $this->id; }
    public function getMember(): ?Member { return $this->member; }
    public function getMemberTank(): ?MemberTank { return $this->memberTank; }
    public function getClubTank(): ?Tank { return $this->clubTank; }
    public function isClubTankBooking(): bool { return $this->clubTank !== null; }
    public function setClubTank(Tank $tank): self { $this->clubTank = $tank; $this->member = null; $this->memberTank = null; return $this; }
    public function getTankCheck(): ?TankCheck { return $this->tankCheck; }
    public function getArticleTitleSnapshot(): ?string { return $this->articleTitleSnapshot; }
    public function getPriceSnapshot(): ?string { return $this->priceSnapshot; }
    public function getPriceBasis(): ?string { return $this->priceBasis; }
    public function setArticleSnapshot(string $title, string $price, string $basis): self
    {
        $this->articleTitleSnapshot = $title;
        $this->priceSnapshot = $price;
        $this->priceBasis = $basis;
        return $this;
    }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): self { $this->status = $status; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function setMember(Member $v): self { $this->member = $v; return $this; }
    public function setMemberTank(MemberTank $v): self { $this->memberTank = $v; return $this; }
    public function setTankCheck(TankCheck $v): self { $this->tankCheck = $v; return $this; }
	public function getMemberDisplay(): string
	{
		return $this->clubTank !== null ? 'Verein' : ($this->member?->getId() !== null
			? (string) $this->member->getId()
			: '–');
	}

	public function getTankDisplay(): string
	{
		return $this->clubTank !== null ? 'Verein: '.($this->clubTank->getInventory() ?? ('#'.$this->clubTank->getId())) : ($this->memberTank?->getLabel() ?? '–');
	}

	public function getCheckDisplay(): string
	{
		return $this->tankCheck?->getCheckDate()?->format('d.m.Y') ?? '–';
	}
}
