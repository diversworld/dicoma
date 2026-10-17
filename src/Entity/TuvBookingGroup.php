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
 public function getId():?int{return $this->id;}
 public function getTankCheck():TankCheck{return $this->tankCheck;}
}
