<?php

declare(strict_types=1);
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'bar_duty_notification')]
#[ORM\UniqueConstraint(name: 'uniq_bar_notification_event', columns: ['event_key'])]
class BarDutyNotification
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column] private ?int $id = null;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')] private Member $member;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')] private BarShift $shift;
    #[ORM\Column(length: 120)] private string $eventKey;
    #[ORM\Column(length: 180)] private string $subject;
    #[ORM\Column(type: 'text')] private string $body;
    #[ORM\Column(type: 'datetime_immutable')] private \DateTimeImmutable $createdAt;
    #[ORM\Column(type: 'datetime_immutable', nullable: true)] private ?\DateTimeImmutable $readAt = null;
    #[ORM\Column(length: 20)] private string $mailState = 'pending';
    #[ORM\Column(length: 500, nullable: true)] private ?string $mailError = null;
    public function __construct(Member $member, BarShift $shift, string $key, string $subject, string $body)
    {
        $this->member = $member; $this->shift = $shift; $this->eventKey = $key; $this->subject = $subject; $this->body = $body; $this->createdAt = new \DateTimeImmutable();
    }
    public function getId(): ?int { return $this->id; }
    public function getMember(): Member { return $this->member; }
    public function getShift(): BarShift { return $this->shift; }
    public function getSubject(): string { return $this->subject; }
    public function getBody(): string { return $this->body; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getReadAt(): ?\DateTimeImmutable { return $this->readAt; }
    public function getMailState(): string { return $this->mailState; }
    public function getMailError(): ?string { return $this->mailError; }
    public function markRead(): void { $this->readAt ??= new \DateTimeImmutable(); }
    public function mailResult(string $state, ?string $error = null): void
    {
        if (!in_array($state, ['pending', 'sending', 'sent', 'failed'], true)) { throw new \LogicException('Invalid notification state.'); }
        $this->mailState = $state; $this->mailError = $error === null ? null : mb_substr($error, 0, 500);
    }
}
