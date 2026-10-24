<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'uniq_sepa_club_reference', columns: ['club_id', 'reference'])]
class SepaMandate
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private Club $club;
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private Member $member;
    #[ORM\Column(length: 35)]
    private string $reference;
    #[ORM\Column(length: 35)]
    private string $creditorIdentifier;
    #[ORM\Column(length: 180)]
    private string $holder;
    #[ORM\Column(length: 255)]
    private string $encryptedIban;
    #[ORM\Column(length: 4)]
    private string $ibanSuffix;
    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $signedAt;
    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $revokedAt = null;
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private MemberDocument $document;

    public function __construct(Member $member, string $reference, string $creditor, string $holder, string $encrypted, string $suffix, \DateTimeImmutable $signed)
    {
        $club = $member->getClub();
        if ($club === null) { throw new \DomainException('Verein fehlt.'); }
        $this->club = $club;
        $this->member = $member;
        $this->reference = $reference;
        $this->creditorIdentifier = $creditor;
        $this->holder = $holder;
        $this->encryptedIban = $encrypted;
        $this->ibanSuffix = $suffix;
        $this->signedAt = $signed;
    }
    public function getId(): ?int { return $this->id; }
    public function getMember(): Member { return $this->member; }
    public function getReference(): string { return $this->reference; }
    public function getCreditorIdentifier(): string { return $this->creditorIdentifier; }
    public function getHolder(): string { return $this->holder; }
    public function getEncryptedIban(): string { return $this->encryptedIban; }
    public function getMaskedIban(): string { return '**** ' . $this->ibanSuffix; }
    public function getSignedAt(): \DateTimeImmutable { return $this->signedAt; }
    public function getRevokedAt(): ?\DateTimeImmutable { return $this->revokedAt; }
    public function setDocument(MemberDocument $document): void { $this->document = $document; }
    public function revoke(\DateTimeImmutable $date): void
    {
        if ($this->revokedAt !== null || $date < $this->signedAt) { throw new \DomainException('Ungültiger Mandatswiderruf.'); }
        $this->revokedAt = $date;
    }
}
