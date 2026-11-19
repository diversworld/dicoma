<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
class MembershipPolicy
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\OneToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private Club $club;
    #[ORM\Column(length: 5, nullable: true)]
    private ?string $cancellationCutoff = null;
    #[ORM\Column(length: 5, nullable: true)]
    private ?string $membershipYearEnd = null;
    #[ORM\Column]
    private bool $applicationsEnabled = false;
    #[ORM\Column(length: 180, nullable: true)]
    #[Assert\Email, Assert\Length(max: 180)]
    private ?string $contactEmail = null;
    #[ORM\Column(type: 'json')]
    private array $requiredDocuments = [];
    #[ORM\Column(length: 35, nullable: true)]
    #[Assert\Length(max: 35)]
    private ?string $creditorIdentifier = null;
    #[ORM\Column(options: ['default' => 1])]
    #[Assert\Range(min: 1, max: 10)]
    private int $requiredApprovalCount = 1;
    #[ORM\Column(length: 40, options: ['default' => User::ROLE_ADMIN])]
    private string $approvalRole = User::ROLE_ADMIN;
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?ContributionClass $onboardingContributionClass = null;
    #[ORM\Column(options: ['default' => false])]
    private bool $onboardingIssueAnnualCharge = false;
    #[ORM\Column(length: 5, nullable: true)]
    private ?string $onboardingChargeDue = null;

    public function __construct(Club $club) { $this->club = $club; }
    public function getId(): ?int { return $this->id; }
    public function getClub(): Club { return $this->club; }
    public function getCancellationCutoff(): ?string { return $this->cancellationCutoff; }
    public function setCancellationCutoff(?string $value): void { $this->cancellationCutoff = $value; }
    public function getMembershipYearEnd(): ?string { return $this->membershipYearEnd; }
    public function setMembershipYearEnd(?string $value): void { $this->membershipYearEnd = $value; }
    public function isApplicationsEnabled(): bool { return $this->applicationsEnabled; }
    public function setApplicationsEnabled(bool $value): void { $this->applicationsEnabled = $value; }
    public function getContactEmail(): ?string { return $this->contactEmail; }
    public function setContactEmail(?string $value): void { $this->contactEmail = $value; }
    public function getRequiredDocuments(): array { return $this->requiredDocuments; }
    public function setRequiredDocuments(array $value): void { $this->requiredDocuments = $value; }
    public function getCreditorIdentifier(): ?string { return $this->creditorIdentifier; }
    public function setCreditorIdentifier(?string $value): void { $this->creditorIdentifier = $value; }
    public function getRequiredApprovalCount(): int { return $this->requiredApprovalCount; }
    public function setRequiredApprovalCount(int $value): void { $this->requiredApprovalCount = $value; }
    public function getApprovalRole(): string { return $this->approvalRole; }
    public function setApprovalRole(string $value): void { $this->approvalRole = $value; }
    public function getOnboardingContributionClass(): ?ContributionClass { return $this->onboardingContributionClass; }
    public function setOnboardingContributionClass(?ContributionClass $value): void { $this->onboardingContributionClass = $value; }
    public function isOnboardingIssueAnnualCharge(): bool { return $this->onboardingIssueAnnualCharge; }
    public function setOnboardingIssueAnnualCharge(bool $value): void { $this->onboardingIssueAnnualCharge = $value; }
    public function getOnboardingChargeDue(): ?string { return $this->onboardingChargeDue; }
    public function setOnboardingChargeDue(?string $value): void { $this->onboardingChargeDue = $value; }

    public function validateRules(): void
    {
        if ($this->requiredApprovalCount < 1 || $this->requiredApprovalCount > 10) {
            throw new \DomainException('Die erforderliche Anzahl der Freigaben muss zwischen 1 und 10 liegen.');
        }
        if (!in_array($this->approvalRole, [User::ROLE_ADMIN, User::ROLE_BOARD, User::ROLE_SUPER_ADMIN], true)) {
            throw new \DomainException('Ungültige Freigaberolle für das Aufnahmeverfahren.');
        }
        if (($this->cancellationCutoff === null) !== ($this->membershipYearEnd === null)) {
            throw new \DomainException('Kündigungsstichtag und Jahresende müssen gemeinsam konfiguriert werden.');
        }
        foreach ([$this->cancellationCutoff, $this->membershipYearEnd] as $value) {
            if ($value !== null && (!preg_match('/^(\d{2})-(\d{2})$/D', $value, $parts)
                || !checkdate((int) $parts[1], (int) $parts[2], 2001))) {
                throw new \DomainException('Stichtage müssen als gültiges MM-TT ohne Schalttag eingegeben werden.');
            }
        }
        if ($this->cancellationCutoff !== null && $this->cancellationCutoff > $this->membershipYearEnd) {
            throw new \DomainException('Der Kündigungsstichtag darf nicht nach dem Mitgliedschaftsjahresende liegen.');
        }
        if ($this->applicationsEnabled && !filter_var($this->contactEmail, FILTER_VALIDATE_EMAIL)) {
            throw new \DomainException('Für öffentliche Anträge ist eine Kontaktadresse der Verwaltung erforderlich.');
        }
        foreach ($this->requiredDocuments as $kind) {
            if (!in_array($kind, array_values(MemberDocument::kinds()), true)) {
                throw new \DomainException('Unbekannte Dokumentanforderung.');
            }
        }
        if ($this->onboardingContributionClass !== null
            && $this->onboardingContributionClass->getClub()->getId() !== $this->club->getId()) {
            throw new \DomainException('Die Onboarding-Beitragsart muss zum Verein gehören.');
        }
        if ($this->onboardingIssueAnnualCharge && $this->onboardingContributionClass === null) {
            throw new \DomainException('Für die automatische Jahresforderung ist eine Onboarding-Beitragsart erforderlich.');
        }
        if ($this->onboardingChargeDue !== null
            && (!preg_match('/^(\d{2})-(\d{2})$/D', $this->onboardingChargeDue, $parts)
                || !checkdate((int) $parts[1], (int) $parts[2], 2001))) {
            throw new \DomainException('Das Fälligkeitsdatum muss als gültiges MM-TT angegeben werden.');
        }
    }

    public function cancellationDate(\DateTimeImmutable $received): \DateTimeImmutable
    {
        $this->validateRules();
        if ($this->cancellationCutoff === null || $this->membershipYearEnd === null) {
            throw new \DomainException('Der Verein hat noch keine Kündigungsregel hinterlegt. Bitte die Verwaltung kontaktieren.');
        }
        $year = (int) $received->format('Y');
        if ($received->format('m-d') > $this->cancellationCutoff) {
            ++$year;
        }
        return new \DateTimeImmutable($year . '-' . $this->membershipYearEnd, $received->getTimezone());
    }
}
