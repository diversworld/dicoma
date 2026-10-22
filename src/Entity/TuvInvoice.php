<?php
declare(strict_types=1);
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;
#[ORM\Entity]
#[ORM\Table(name:'tuv_invoice')]
#[ORM\UniqueConstraint(name:'uniq_tuv_invoice_booking',columns:['booking_id'])]
#[ORM\UniqueConstraint(name:'uniq_tuv_invoice_number',columns:['number'])]
class TuvInvoice {
 #[ORM\Id,ORM\GeneratedValue,ORM\Column] private ?int $id=null;
 #[ORM\ManyToOne(targetEntity:MemberTankCheckBooking::class)]
 #[ORM\JoinColumn(nullable:false,onDelete:'RESTRICT')] private MemberTankCheckBooking $booking;
 #[ORM\Column(length:40,nullable:true)] private ?string $number=null;
 #[ORM\Column(length:180)] private string $recipient;
 #[ORM\Column(length:180)] private string $issuer;
 #[ORM\Column(type:'datetime_immutable')] private \DateTimeImmutable $issuedAt;
 #[ORM\Column(type:'json')] private array $lines;
 #[ORM\Column(type:'integer')] private int $netCents;
 #[ORM\Column(type:'integer')] private int $grossCents;
 public function __construct(MemberTankCheckBooking $booking,string $recipient,string $issuer,array $lines,int $netCents,int $grossCents) {
  $this->booking=$booking;$this->recipient=$recipient;$this->issuer=$issuer;$this->lines=$lines;$this->netCents=$netCents;$this->grossCents=$grossCents;$this->issuedAt=new \DateTimeImmutable();
 }
 public function assignNumber():void {if ($this->number!==null || $this->id===null) throw new \LogicException('Rechnungsnummer nicht zuweisbar');$this->number='TUV-'.$this->issuedAt->format('Y').'-'.str_pad((string)$this->id,6,'0',STR_PAD_LEFT);}
 public function getId():?int{return $this->id;}
 public function getBooking():MemberTankCheckBooking{return $this->booking;}
 public function getNumber():?string{return $this->number;}
 public function getRecipient():string{return $this->recipient;}
 public function getIssuer():string{return $this->issuer;}
 public function getIssuedAt():\DateTimeImmutable{return $this->issuedAt;}
 public function getLines():array{return $this->lines;}
 public function getNetCents():int{return $this->netCents;}
 public function getGrossCents():int{return $this->grossCents;}
}
