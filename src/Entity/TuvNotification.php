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
 #[ORM\Column(name:'attempt_count',type:'integer',options:['default'=>0])] private int $attemptCount=0;
 #[ORM\Column(name:'last_error',type:'text',nullable:true)] private ?string $lastError=null;
 #[ORM\Column(name:'last_attempt_at',type:'datetime_immutable',nullable:true)] private ?\DateTimeImmutable $lastAttemptAt=null;
 public function getAttemptCount():int{return $this->attemptCount;}
 public function getLastError():?string{return $this->lastError;}
 public function getLastAttemptAt():?\DateTimeImmutable{return $this->lastAttemptAt;}
 public function markAttempt():void{$this->attemptCount++;$this->lastAttemptAt=new \DateTimeImmutable();}
 public function markFailed(string $message):void{$this->state='failed';$this->lastError=mb_substr($message,0,2000);}
 public function resetForRetry():void{if($this->state!=='failed'){throw new \LogicException('Nur fehlgeschlagene Nachrichten dürfen erneut vorgemerkt werden.');}$this->state='pending';$this->lastError=null;}
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
 public function markSent():void{$this->state='sent';$this->lastError=null;$this->sentAt=new \DateTimeImmutable();}
}
