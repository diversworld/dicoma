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
    #[ORM\Column(length: 40, nullable: true)]
    private ?string $invoiceNumber = null;
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $invoiceIssuedAt = null;
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastReminderAt = null;
    #[ORM\Column(options: ['default' => 0])]
    private int $reminderCount = 0;

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
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getInvoiceNumber(): ?string { return $this->invoiceNumber; }
    public function getInvoiceIssuedAt(): ?\DateTimeImmutable { return $this->invoiceIssuedAt; }
    public function getLastReminderAt(): ?\DateTimeImmutable { return $this->lastReminderAt; }
    public function getReminderCount(): int { return $this->reminderCount; }
    public function issueInvoice(string $number): void
    {
        if ($this->invoiceNumber !== null) { throw new \DomainException('Rechnung wurde bereits erstellt.'); }
        $number = trim($number);
        if ($number === '' || mb_strlen($number) > 40) { throw new \DomainException('Ungültige Rechnungsnummer.'); }
        $this->invoiceNumber = $number; $this->invoiceIssuedAt = new \DateTimeImmutable();
    }
    public function markReminder(): void { $this->lastReminderAt = new \DateTimeImmutable(); ++$this->reminderCount; }
    public function getStatus(\DateTimeImmutable $today = new \DateTimeImmutable('today')): string
    {
        if ($this->getOutstandingCents() <= 0) { return 'paid'; }
        if ($this->dueAt->format('Y-m-d') < $today->format('Y-m-d')) { return 'overdue'; }
        return 'open';
    }
}
