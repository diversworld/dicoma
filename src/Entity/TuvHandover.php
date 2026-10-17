<?php
declare(strict_types=1);
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;
#[ORM\Entity]
#[ORM\Table(name: 'tuv_handover')]
class TuvHandover {
 #[ORM\Id, ORM\GeneratedValue, ORM\Column] private ?int $id=null;
 #[ORM\ManyToOne(targetEntity: MemberTankCheckBooking::class)]
 #[ORM\JoinColumn(nullable:false,onDelete:'CASCADE')] private MemberTankCheckBooking $booking;
 #[ORM\Column(length:20)] private string $eventType;
 #[ORM\Column(type:'datetime_immutable')] private \DateTimeImmutable $occurredAt;
 #[ORM\Column(length:180)] private string $actor;
 #[ORM\Column(type:'text',nullable:true)] private ?string $notes;
 public function __construct(MemberTankCheckBooking $booking,string $eventType,string $actor,?string $notes) {
  if(!in_array($eventType,['received','sent','returned','collected'],true)) throw new \InvalidArgumentException('Ungültige Übergabeart');
  $this->booking=$booking;$this->eventType=$eventType;$this->actor=mb_substr($actor,0,180);$this->notes=$notes;$this->occurredAt=new \DateTimeImmutable();
 }
 public function getEventType():string{return $this->eventType;}
 public function getOccurredAt():\DateTimeImmutable{return $this->occurredAt;}
 public function getActor():string{return $this->actor;}
 public function getNotes():?string{return $this->notes;}
}
