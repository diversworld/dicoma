<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'uniq_production_approval_club', columns: ['club_id'])]
class ProductionApproval
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private Club $club;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $sepaApprovedAt = null;
    #[ORM\Column(length: 180, nullable: true)]
    private ?string $sepaApprovedBy = null;
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $sepaEvidence = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $chartApprovedAt = null;
    #[ORM\Column(length: 180, nullable: true)]
    private ?string $chartApprovedBy = null;
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $chartEvidence = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $retentionApprovedAt = null;
    #[ORM\Column(length: 180, nullable: true)]
    private ?string $retentionApprovedBy = null;
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $retentionEvidence = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $wcagApprovedAt = null;
    #[ORM\Column(length: 180, nullable: true)]
    private ?string $wcagApprovedBy = null;
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $wcagEvidence = null;

    public function __construct(Club $club) { $this->club = $club; }
    public function getId(): ?int { return $this->id; }
    public function getClub(): Club { return $this->club; }
    public function getSepaApprovedAt(): ?\DateTimeImmutable { return $this->sepaApprovedAt; }
    public function getSepaApprovedBy(): ?string { return $this->sepaApprovedBy; }
    public function getSepaEvidence(): ?string { return $this->sepaEvidence; }
    public function getChartApprovedAt(): ?\DateTimeImmutable { return $this->chartApprovedAt; }
    public function getChartApprovedBy(): ?string { return $this->chartApprovedBy; }
    public function getChartEvidence(): ?string { return $this->chartEvidence; }
    public function getRetentionApprovedAt(): ?\DateTimeImmutable { return $this->retentionApprovedAt; }
    public function getRetentionApprovedBy(): ?string { return $this->retentionApprovedBy; }
    public function getRetentionEvidence(): ?string { return $this->retentionEvidence; }
    public function getWcagApprovedAt(): ?\DateTimeImmutable { return $this->wcagApprovedAt; }
    public function getWcagApprovedBy(): ?string { return $this->wcagApprovedBy; }
    public function getWcagEvidence(): ?string { return $this->wcagEvidence; }

    public function approve(string $area, string $actor, string $evidence): void
    {
        $evidence = trim($evidence);
        if (trim($actor) === '' || $evidence === '' || mb_strlen($evidence) > 255) {
            throw new \DomainException('Freigabe benötigt eine verantwortliche Person und einen prüfbaren Nachweis.');
        }
        $now = new \DateTimeImmutable();
        if ($area === 'sepa') {
            $this->sepaApprovedAt = $now; $this->sepaApprovedBy = trim($actor); $this->sepaEvidence = $evidence;
        } elseif ($area === 'chart') {
            $this->chartApprovedAt = $now; $this->chartApprovedBy = trim($actor); $this->chartEvidence = $evidence;
        } elseif ($area === 'retention') {
            $this->retentionApprovedAt = $now; $this->retentionApprovedBy = trim($actor); $this->retentionEvidence = $evidence;
        } elseif ($area === 'wcag') {
            $this->wcagApprovedAt = $now; $this->wcagApprovedBy = trim($actor); $this->wcagEvidence = $evidence;
        } else {
            throw new \DomainException('Unbekannter Freigabebereich.');
        }
    }
}
