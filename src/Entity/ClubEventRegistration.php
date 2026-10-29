<?php

declare(strict_types=1);
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'uniq_event_person', columns: ['event_id', 'person_id'])]
#[ORM\Index(name: 'idx_event_registration_waiting', columns: ['event_id', 'status', 'registered_at'])]
class ClubEventRegistration
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column] private ?int $id = null;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')] private ClubEvent $event;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')] private Person $person;
    #[ORM\Column(length: 20)] private string $status;
    #[ORM\Column] private int $feeCents;
    #[ORM\Column(length: 10)] private string $tariff;
    #[ORM\Column(type: 'datetime_immutable')] private \DateTimeImmutable $registeredAt;
    #[ORM\Column(type: 'datetime_immutable', nullable: true)] private ?\DateTimeImmutable $cancelledAt = null;

    public function __construct(ClubEvent $event, Person $person, string $status, int $feeCents, string $tariff)
    {
        $this->event = $event; $this->person = $person; $this->status = $status;
        $this->feeCents = $feeCents; $this->tariff = $tariff; $this->registeredAt = new \DateTimeImmutable();
    }
    public function getId(): ?int { return $this->id; }
    public function getEvent(): ClubEvent { return $this->event; }
    public function getPerson(): Person { return $this->person; }
    public function getStatus(): string { return $this->status; }
    public function getFeeCents(): int { return $this->feeCents; }
    public function getTariff(): string { return $this->tariff; }
    public function getRegisteredAt(): \DateTimeImmutable { return $this->registeredAt; }
    public function getCancelledAt(): ?\DateTimeImmutable { return $this->cancelledAt; }
    public function cancel(): void { $this->status = 'cancelled'; $this->cancelledAt = new \DateTimeImmutable(); }
    public function promote(): void { $this->status = 'registered'; }
    public function disqualify(): void { $this->status = 'ineligible'; }
}
