<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'uniq_membership_dunning_stage', columns: ['charge_id', 'stage'])]
class MembershipDunningNotice
{
    public const STAGE_REMINDER = 'reminder';
    public const STAGE_FIRST = 'first';
    public const STAGE_SECOND = 'second';
    public const STAGE_FINAL = 'final';

    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private MembershipCharge $charge;

    #[ORM\Column(length: 20)]
    private string $stage;

    #[ORM\Column(length: 180)]
    private string $subject;

    #[ORM\Column(type: 'text')]
    private string $body;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $nextActionAt;

    public function __construct(MembershipCharge $charge, string $stage, string $subject, string $body, \DateTimeImmutable $nextActionAt)
    {
        if (!in_array($stage, self::stages(), true)) {
            throw new \InvalidArgumentException('Unbekannte Mahnstufe.');
        }
        $this->charge = $charge;
        $this->stage = $stage;
        $this->subject = trim($subject);
        $this->body = trim($body);
        $this->createdAt = new \DateTimeImmutable();
        $this->nextActionAt = $nextActionAt;
    }

    public function getId(): ?int { return $this->id; }
    public function getCharge(): MembershipCharge { return $this->charge; }
    public function getStage(): string { return $this->stage; }
    public function getSubject(): string { return $this->subject; }
    public function getBody(): string { return $this->body; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getNextActionAt(): \DateTimeImmutable { return $this->nextActionAt; }
    /** @return list<string> */
    public static function stages(): array { return [self::STAGE_REMINDER, self::STAGE_FIRST, self::STAGE_SECOND, self::STAGE_FINAL]; }
}
