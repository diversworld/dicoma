<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class MembershipPayment
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private MembershipCharge $charge;
    #[ORM\Column]
    private int $amountCents;
    #[ORM\Column(length: 180)]
    private string $reference;
    #[ORM\Column(length: 180)]
    private string $actor;
    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $receivedAt;

    public function __construct(MembershipCharge $charge, int $cents, string $reference, string $actor)
    {
        $this->charge = $charge;
        $this->amountCents = $cents;
        $this->reference = $reference;
        $this->actor = $actor;
        $this->receivedAt = new \DateTimeImmutable();
    }
    public function getAmountCents(): int { return $this->amountCents; }
    public function getReference(): string { return $this->reference; }
    public function getActor(): string { return $this->actor; }
    public function getReceivedAt(): \DateTimeImmutable { return $this->receivedAt; }
}
