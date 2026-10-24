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

    public function validateRules(): void
    {
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
