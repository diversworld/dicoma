<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'uniq_sepa_batch_charge', columns: ['batch_id', 'charge_id'])]
class SepaDirectDebitItem
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\ManyToOne(inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private SepaDirectDebitBatch $batch;
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private MembershipCharge $charge;
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private SepaMandate $mandate;
    #[ORM\Column]
    private int $amountCents;
    public function __construct(MembershipCharge $charge, SepaMandate $mandate, int $amountCents)
    {
        if ($amountCents <= 0 || $amountCents > $charge->getOutstandingCents()) { throw new \DomainException('Ungültiger Einzugsbetrag.'); }
        $this->charge = $charge;
        $this->mandate = $mandate;
        $this->amountCents = $amountCents;
    }
    public function setBatch(SepaDirectDebitBatch $batch): void { $this->batch = $batch; }
    public function getId(): ?int { return $this->id; }
    public function getCharge(): MembershipCharge { return $this->charge; }
    public function getMandate(): SepaMandate { return $this->mandate; }
    public function getAmountCents(): int { return $this->amountCents; }
}
