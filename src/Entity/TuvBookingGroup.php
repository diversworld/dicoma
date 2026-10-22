<?php
declare(strict_types=1);
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;
#[ORM\Entity]
#[ORM\Table(name:'tuv_booking_group')]
class TuvBookingGroup {
 #[ORM\Id, ORM\GeneratedValue, ORM\Column] private ?int $id=null;
 #[ORM\ManyToOne(targetEntity:TankCheck::class)]
 #[ORM\JoinColumn(nullable:false,onDelete:'RESTRICT')] private TankCheck $tankCheck;
 #[ORM\Column(length:180)] private string $createdBy;
 #[ORM\Column(type:'datetime_immutable')] private \DateTimeImmutable $createdAt;
 public function __construct(TankCheck $check,string $actor){$this->tankCheck=$check;$this->createdBy=mb_substr($actor,0,180);$this->createdAt=new \DateTimeImmutable();}
 #[ORM\Column(name:'agency_confirmed_at',type:'datetime_immutable',nullable:true)] private ?\DateTimeImmutable $agencyConfirmedAt=null;
 #[ORM\Column(name:'agency_text_version',length:40,nullable:true)] private ?string $agencyTextVersion=null;
 public function confirmAgency(string $version):void{$this->agencyConfirmedAt=new \DateTimeImmutable();$this->agencyTextVersion=$version;}
 public function getAgencyConfirmedAt():?\DateTimeImmutable{return $this->agencyConfirmedAt;}
 public function getAgencyTextVersion():?string{return $this->agencyTextVersion;}
 public function getId():?int{return $this->id;}
 public function getTankCheck():TankCheck{return $this->tankCheck;}
}
