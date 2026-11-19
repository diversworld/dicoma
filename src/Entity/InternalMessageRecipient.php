<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'uniq_internal_message_recipient', columns: ['message_id', 'member_id'])]
#[ORM\Index(name: 'idx_internal_message_recipient_member_read', columns: ['member_id', 'read_at'])]
#[ORM\Index(name: 'IDX_internal_message_recipient_message', columns: ['message_id'])]
class InternalMessageRecipient
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'recipients'), ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private InternalMessage $message;

    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private Member $member;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $readAt = null;

    public function __construct(InternalMessage $message, Member $member)
    {
        if ($member->getClub()?->getId() !== $message->getClub()->getId()) {
            throw new \InvalidArgumentException('Empfänger und Nachricht gehören nicht zum selben Verein.');
        }
        $this->message = $message;
        $this->member = $member;
        $message->addRecipient($this);
    }

    public function getId(): ?int { return $this->id; }
    public function getMessage(): InternalMessage { return $this->message; }
    public function getMember(): Member { return $this->member; }
    public function getReadAt(): ?\DateTimeImmutable { return $this->readAt; }
    public function markRead(): void { $this->readAt ??= new \DateTimeImmutable(); }
}
