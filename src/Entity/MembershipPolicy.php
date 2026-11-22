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
    #[ORM\Column(length: 35, nullable: true)]
    private ?string $sepaCreditorName = null;
    #[ORM\Column(length: 34, nullable: true)]
    private ?string $sepaCreditorIban = null;
    #[ORM\Column(length: 11, nullable: true)]
    private ?string $sepaCreditorBic = null;
    #[ORM\Column(type: 'json')]
    private array $dunningIntervals = ['reminder' => 14, 'first' => 28, 'second' => 42, 'final' => 56];
    #[ORM\Column(type: 'json')]
    private array $dunningTemplates = [
        'reminder' => "Zahlungserinnerung: {{ description }}\nOffener Betrag: {{ amount }} EUR\nFällig seit: {{ dueDate }}\nRechnung: {{ invoice }}",
        'first' => "Erste Mahnung: {{ description }}\nOffener Betrag: {{ amount }} EUR\nFällig seit: {{ dueDate }}\nRechnung: {{ invoice }}",
        'second' => "Zweite Mahnung: {{ description }}\nOffener Betrag: {{ amount }} EUR\nFällig seit: {{ dueDate }}\nRechnung: {{ invoice }}",
        'final' => "Letzte Mahnung: {{ description }}\nOffener Betrag: {{ amount }} EUR\nFällig seit: {{ dueDate }}\nRechnung: {{ invoice }}",
    ];

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
    public function getSepaCreditorName(): ?string { return $this->sepaCreditorName; }
    public function setSepaCreditorName(?string $value): void { $this->sepaCreditorName = $value === null ? null : trim($value); }
    public function getSepaCreditorIban(): ?string { return $this->sepaCreditorIban; }
    public function setSepaCreditorIban(?string $value): void { $this->sepaCreditorIban = $value === null ? null : strtoupper(str_replace(' ', '', trim($value))); }
    public function getSepaCreditorBic(): ?string { return $this->sepaCreditorBic; }
    public function setSepaCreditorBic(?string $value): void { $this->sepaCreditorBic = $value === null ? null : strtoupper(trim($value)); }
    public function getDunningIntervals(): array { return $this->dunningIntervals; }
    public function setDunningIntervals(array $value): void { $this->dunningIntervals = $value; }
    public function getDunningTemplates(): array { return $this->dunningTemplates; }
    public function setDunningTemplates(array $value): void { $this->dunningTemplates = $value; }
    public function getDunningReminderDays(): int { return (int) ($this->dunningIntervals['reminder'] ?? 14); }
    public function setDunningReminderDays(int $value): void { $this->dunningIntervals['reminder'] = $value; }
    public function getDunningFirstDays(): int { return (int) ($this->dunningIntervals['first'] ?? 28); }
    public function setDunningFirstDays(int $value): void { $this->dunningIntervals['first'] = $value; }
    public function getDunningSecondDays(): int { return (int) ($this->dunningIntervals['second'] ?? 42); }
    public function setDunningSecondDays(int $value): void { $this->dunningIntervals['second'] = $value; }
    public function getDunningFinalDays(): int { return (int) ($this->dunningIntervals['final'] ?? 56); }
    public function setDunningFinalDays(int $value): void { $this->dunningIntervals['final'] = $value; }
    public function getDunningReminderTemplate(): string { return (string) ($this->dunningTemplates['reminder'] ?? ''); }
    public function setDunningReminderTemplate(string $value): void { $this->dunningTemplates['reminder'] = $value; }
    public function getDunningFirstTemplate(): string { return (string) ($this->dunningTemplates['first'] ?? ''); }
    public function setDunningFirstTemplate(string $value): void { $this->dunningTemplates['first'] = $value; }
    public function getDunningSecondTemplate(): string { return (string) ($this->dunningTemplates['second'] ?? ''); }
    public function setDunningSecondTemplate(string $value): void { $this->dunningTemplates['second'] = $value; }
    public function getDunningFinalTemplate(): string { return (string) ($this->dunningTemplates['final'] ?? ''); }
    public function setDunningFinalTemplate(string $value): void { $this->dunningTemplates['final'] = $value; }

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
        $stages = ['reminder', 'first', 'second', 'final'];
        $last = -1;
        foreach ($stages as $stage) {
            $day = $this->dunningIntervals[$stage] ?? null;
            if (!is_int($day) || $day < 0 || $day <= $last || $day > 3650) {
                throw new \DomainException('Mahnstufen müssen lückenlos und in aufsteigender Reihenfolge konfiguriert sein.');
            }
            $last = $day;
            $template = $this->dunningTemplates[$stage] ?? null;
            if (!is_string($template) || trim($template) === '' || mb_strlen($template) > 4000) {
                throw new \DomainException('Für jede Mahnstufe ist eine Vorlage erforderlich.');
            }
        }
        if (($this->sepaCreditorName !== null || $this->sepaCreditorIban !== null || $this->sepaCreditorBic !== null)
            && ($this->creditorIdentifier === null || $this->sepaCreditorName === null || $this->sepaCreditorIban === null || $this->sepaCreditorBic === null
                || !preg_match('/^[A-Z]{2}[0-9A-Z]{13,32}$/D', $this->sepaCreditorIban)
                || !preg_match('/^[A-Z0-9]{8}([A-Z0-9]{3})?$/D', $this->sepaCreditorBic))) {
            throw new \DomainException('Für den SEPA-Export sind Gläubiger-ID, Name, gültige IBAN und BIC gemeinsam erforderlich.');
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
