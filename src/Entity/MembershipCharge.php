<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'uniq_membership_charge_key', columns: ['charge_key'])]
class MembershipCharge
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private Member $member;
    #[ORM\Column(length: 100)]
    private string $chargeKey;
    #[ORM\Column(length: 180)]
    private string $description;
    #[ORM\Column]
    private int $amountCents;
    #[ORM\Column]
    private int $paidCents = 0;
    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $dueAt;
    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(Member $member, string $key, string $description, int $cents, \DateTimeImmutable $due)
    {
        if ($cents < 0 || $cents > 100000000 || trim($description) === '' || mb_strlen($description) > 180) {
            throw new \DomainException('Ungültiger Beitragsbetrag oder Forderungstext.');
        }
        $this->member = $member;
        $this->chargeKey = $key;
        $this->description = $description;
        $this->amountCents = $cents;
        $this->dueAt = $due;
        $this->createdAt = new \DateTimeImmutable();
    }
    public function getId(): ?int { return $this->id; }
    public function getMember(): Member { return $this->member; }
    public function getDescription(): string { return $this->description; }
    public function getAmountCents(): int { return $this->amountCents; }
    public function getPaidCents(): int { return $this->paidCents; }
    public function getOutstandingCents(): int { return $this->amountCents - $this->paidCents; }
    public function getDueAt(): \DateTimeImmutable { return $this->dueAt; }
}
