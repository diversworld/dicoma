<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'uniq_membership_message_key', columns: ['message_key'])]
class MembershipMessage
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\Column(length: 150)]
    private string $messageKey;
    #[ORM\Column(length: 180)]
    private string $recipient;
    #[ORM\Column(length: 180)]
    private string $subject;
    #[ORM\Column(type: 'text')]
    private string $body;
    #[ORM\Column(length: 20)]
    private string $state = 'pending';
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $error = null;
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $sentAt = null;

    public function __construct(string $key, string $recipient, string $subject, string $body)
    {
        $this->messageKey = $key;
        $this->recipient = $recipient;
        $this->subject = $subject;
        $this->body = $body;
    }
    public function getId(): ?int { return $this->id; }
    public function getRecipient(): string { return $this->recipient; }
    public function getSubject(): string { return $this->subject; }
    public function getBody(): string { return $this->body; }
    public function getState(): string { return $this->state; }
    public function sent(): void { $this->state = 'sent'; $this->error = null; $this->sentAt = new \DateTimeImmutable(); }
    public function fail(string $error): void { $this->state = 'failed'; $this->error = $error; }
}
