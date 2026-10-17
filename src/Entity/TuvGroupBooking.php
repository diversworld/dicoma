<?php
declare(strict_types=1);
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;
#[ORM\Entity]
#[ORM\Table(name:'tuv_group_booking')]
#[ORM\UniqueConstraint(name:'uniq_tuv_group_booking',columns:['booking_id'])]
class TuvGroupBooking {
 #[ORM\Id, ORM\GeneratedValue, ORM\Column] private ?int $id=null;
 #[ORM\ManyToOne(targetEntity:TuvBookingGroup::class)]
 #[ORM\JoinColumn(nullable:false,onDelete:'CASCADE')] private TuvBookingGroup $group;
 #[ORM\ManyToOne(targetEntity:MemberTankCheckBooking::class)]
 #[ORM\JoinColumn(nullable:false,onDelete:'CASCADE')] private MemberTankCheckBooking $booking;
 public function __construct(TuvBookingGroup $group,MemberTankCheckBooking $booking){$this->group=$group;$this->booking=$booking;}
 public function getBooking():MemberTankCheckBooking{return $this->booking;}
 public function getGroup():TuvBookingGroup{return $this->group;}
}
