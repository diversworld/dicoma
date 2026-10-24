<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
class MembershipApplication
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\Version, ORM\Column(type: 'integer')]
    private int $version = 1;
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private Club $club;
    #[ORM\Column(length: 100)]
    #[Assert\NotBlank, Assert\Length(max: 100)]
    private string $firstname = '';
    #[ORM\Column(length: 100)]
    #[Assert\NotBlank, Assert\Length(max: 100)]
    private string $lastname = '';
    #[ORM\Column(length: 100)]
    #[Assert\NotBlank, Assert\Email, Assert\Length(max: 100)]
    private string $email = '';
    #[ORM\Column(type: 'date_immutable')]
    #[Assert\NotNull, Assert\LessThanOrEqual('today')]
    private ?\DateTimeImmutable $birthday = null;
    #[ORM\Column(length: 100)]
    #[Assert\NotBlank, Assert\Length(max: 100)]
    private string $street = '';
    #[ORM\Column(length: 20)]
    #[Assert\NotBlank, Assert\Length(max: 20)]
    private string $postalCode = '';
    #[ORM\Column(length: 120)]
    #[Assert\NotBlank, Assert\Length(max: 120)]
    private string $city = '';
    #[ORM\Column(length: 20)]
    private string $status = 'pending';
    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $submittedAt;
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $decidedAt = null;
    #[ORM\Column(length: 180, nullable: true)]
    private ?string $decidedBy = null;
    #[ORM\Column(length: 1000, nullable: true)]
    private ?string $decisionNote = null;
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'RESTRICT')]
    private ?Member $member = null;

    public function __construct(Club $club) { $this->club = $club; $this->submittedAt = new \DateTimeImmutable(); }
    public function getId(): ?int { return $this->id; }
    public function getClub(): Club { return $this->club; }
    public function getFirstname(): string { return $this->firstname; }
    public function setFirstname(string $value): void { $this->firstname = trim($value); }
    public function getLastname(): string { return $this->lastname; }
    public function setLastname(string $value): void { $this->lastname = trim($value); }
    public function getEmail(): string { return $this->email; }
    public function setEmail(string $value): void { $this->email = trim($value); }
    public function getBirthday(): ?\DateTimeImmutable { return $this->birthday; }
    public function setBirthday(?\DateTimeImmutable $value): void { $this->birthday = $value; }
    public function getStreet(): string { return $this->street; }
    public function setStreet(string $value): void { $this->street = trim($value); }
    public function getPostalCode(): string { return $this->postalCode; }
    public function setPostalCode(string $value): void { $this->postalCode = trim($value); }
    public function getCity(): string { return $this->city; }
    public function setCity(string $value): void { $this->city = trim($value); }
    public function getStatus(): string { return $this->status; }
    public function getSubmittedAt(): \DateTimeImmutable { return $this->submittedAt; }
    public function getDecidedAt(): ?\DateTimeImmutable { return $this->decidedAt; }
    public function getDecidedBy(): ?string { return $this->decidedBy; }
    public function getDecisionNote(): ?string { return $this->decisionNote; }
    public function getMember(): ?Member { return $this->member; }
    public function decide(?Member $member, string $actor, string $note): void
    {
        if ($this->status !== 'pending') {
            throw new \DomainException('Dieser Antrag wurde bereits entschieden.');
        }
        if (trim($note) === '' || mb_strlen($note) > 1000) {
            throw new \DomainException('Eine dokumentierte Identitätsprüfung bzw. Ablehnungsbegründung ist erforderlich (maximal 1.000 Zeichen).');
        }
        $this->member = $member;
        $this->status = $member === null ? 'rejected' : 'approved';
        $this->decidedAt = new \DateTimeImmutable();
        $this->decidedBy = $actor;
        $this->decisionNote = trim($note);
    }
}
