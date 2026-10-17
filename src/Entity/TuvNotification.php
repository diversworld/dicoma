<?php
declare(strict_types=1);
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;
#[ORM\Entity]
#[ORM\Table(name:'tuv_notification')]
#[ORM\UniqueConstraint(name:'uniq_tuv_notification_key',columns:['dedupe_key'])]
class TuvNotification {
 #[ORM\Id, ORM\GeneratedValue, ORM\Column] private ?int $id=null;
 #[ORM\ManyToOne(targetEntity:MemberTankCheckBooking::class)]
 #[ORM\JoinColumn(nullable:false,onDelete:'CASCADE')] private MemberTankCheckBooking $booking;
 #[ORM\Column(name:'dedupe_key',length:120)] private string $dedupeKey;
 #[ORM\Column(length:180)] private string $recipient;
 #[ORM\Column(length:200)] private string $subject;
 #[ORM\Column(type:'text')] private string $body;
 #[ORM\Column(length:20)] private string $state='pending';
 #[ORM\Column(type:'datetime_immutable')] private \DateTimeImmutable $createdAt;
 #[ORM\Column(type:'datetime_immutable',nullable:true)] private ?\DateTimeImmutable $sentAt=null;
 public function __construct(MemberTankCheckBooking $booking,string $key,string $recipient,string $subject,string $body){$this->booking=$booking;$this->dedupeKey=$key;$this->recipient=$recipient;$this->subject=$subject;$this->body=$body;$this->createdAt=new \DateTimeImmutable();}
 public function getId():?int{return $this->id;}
 public function getDedupeKey():string{return $this->dedupeKey;}
 public function getCreatedAt():\DateTimeImmutable{return $this->createdAt;}
 public function getSentAt():?\DateTimeImmutable{return $this->sentAt;}
 public function getBooking():MemberTankCheckBooking{return $this->booking;}
 public function getBookingNumber():string{return (string)$this->booking->getId();}
 public function getRecipient():string{return $this->recipient;}
 public function getSubject():string{return $this->subject;}
 public function getBody():string{return $this->body;}
 public function getState():string{return $this->state;}
 public function markSent():void{$this->state='sent';$this->sentAt=new \DateTimeImmutable();}
}
