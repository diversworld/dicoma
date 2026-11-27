<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Index(name: 'idx_internal_message_club_sent', columns: ['club_id', 'sent_at'])]
#[ORM\Index(name: 'IDX_internal_message_sender', columns: ['sender_id'])]
#[ORM\Index(name: 'IDX_internal_message_division', columns: ['division_id'])]
#[ORM\Index(name: 'idx_internal_message_template_club_sent', columns: ['club_id', 'audience', 'sent_at'])]
class InternalMessage
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private Club $club;

    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private User $sender;

    #[ORM\ManyToOne, ORM\JoinColumn(nullable: true, onDelete: 'RESTRICT')]
    private ?Sport $division = null;

    #[ORM\Column(length: 20)]
    private string $audience;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $targetRole = null;

    #[ORM\Column(length: 180)]
    private string $subject;

    #[ORM\Column(type: 'text')]
    private string $body;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $sentAt;

    /** @var Collection<int, InternalMessageRecipient> */
    #[ORM\OneToMany(mappedBy: 'message', targetEntity: InternalMessageRecipient::class, cascade: ['persist', 'remove'])]
    private Collection $recipients;

    public function __construct(Club $club, User $sender, string $audience, string $subject, string $body)
    {
        if (!in_array($audience, ['member', 'division', 'role', 'all', 'youth', 'honorary', 'board', 'division_leaders'], true)) {
            throw new \InvalidArgumentException('Ungültige Empfängergruppe.');
        }
        $subject = trim($subject);
        $body = trim($body);
        if ($subject === '' || mb_strlen($subject) > 180 || $body === '' || mb_strlen($body) > 20000) {
            throw new \InvalidArgumentException('Betreff oder Nachrichtentext ist ungültig.');
        }
        $this->club = $club;
        $this->sender = $sender;
        $this->audience = $audience;
        $this->subject = $subject;
        $this->body = $body;
        $this->sentAt = new \DateTimeImmutable();
        $this->recipients = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getClub(): Club { return $this->club; }
    public function getSender(): User { return $this->sender; }
    public function getDivision(): ?Sport { return $this->division; }
    public function setDivision(?Sport $division): void { $this->division = $division; }
    public function getAudience(): string { return $this->audience; }
    public function getTargetRole(): ?string { return $this->targetRole; }
    public function setTargetRole(?string $role): void { $this->targetRole = $role; }
    public function getSubject(): string { return $this->subject; }
    public function getBody(): string { return $this->body; }
    public function getSentAt(): \DateTimeImmutable { return $this->sentAt; }

    public function addRecipient(InternalMessageRecipient $recipient): void
    {
        if ($recipient->getMessage() !== $this || $this->recipients->contains($recipient)) {
            throw new \InvalidArgumentException('Ungültiger Nachrichtenempfänger.');
        }
        $this->recipients->add($recipient);
    }
}
