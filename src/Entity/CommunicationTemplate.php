<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'uniq_communication_template_code', columns: ['club_id', 'code'])]
class CommunicationTemplate
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private Club $club;
    #[ORM\Column(length: 60)]
    private string $code;
    #[ORM\Column(length: 120)]
    private string $name;
    #[ORM\Column(length: 180)]
    private string $subject;
    #[ORM\Column(type: 'text')]
    private string $body;
    #[ORM\Column]
    private bool $active = true;
    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;
    public function __construct(Club $club, string $code, string $name, string $subject, string $body)
    {
        $this->club = $club;
        $this->code = trim($code);
        $this->name = trim($name);
        $this->subject = trim($subject);
        $this->body = trim($body);
        if ($this->code === '' || $this->name === '' || $this->subject === '' || $this->body === '') { throw new \DomainException('Vorlage ist unvollständig.'); }
        $this->updatedAt = new \DateTimeImmutable();
    }
    public function __toString(): string { return $this->name; }
    public function getId(): ?int { return $this->id; }
    public function getClub(): Club { return $this->club; }
    public function getCode(): string { return $this->code; }
    public function getName(): string { return $this->name; }
    public function getSubject(): string { return $this->subject; }
    public function setSubject(string $subject): void { $this->subject = trim($subject); $this->updatedAt = new \DateTimeImmutable(); }
    public function getBody(): string { return $this->body; }
    public function setBody(string $body): void { $this->body = trim($body); $this->updatedAt = new \DateTimeImmutable(); }
    public function isActive(): bool { return $this->active; }
    public function setActive(bool $active): void { $this->active = $active; $this->updatedAt = new \DateTimeImmutable(); }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
}
