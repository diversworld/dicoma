<?php
declare(strict_types=1);
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;
#[ORM\Entity]
#[ORM\Table(name:'tuv_document')]
class TuvDocument {
 #[ORM\Id, ORM\GeneratedValue, ORM\Column] private ?int $id=null;
 #[ORM\ManyToOne(targetEntity:MemberTankCheckBooking::class)]
 #[ORM\JoinColumn(nullable:false,onDelete:'CASCADE')] private MemberTankCheckBooking $booking;
 #[ORM\Column(length:80)] private string $storedName;
 #[ORM\Column(length:255)] private string $originalName;
 #[ORM\Column(length:80)] private string $mime;
 #[ORM\Column] private int $size;
 #[ORM\Column(length:64)] private string $sha256;
 #[ORM\Column(length:180)] private string $uploadedBy;
 #[ORM\Column(type:'datetime_immutable')] private \DateTimeImmutable $uploadedAt;
 public function __construct(MemberTankCheckBooking $booking,string $storedName,string $originalName,string $mime,int $size,string $sha256,string $actor){
  $this->booking=$booking;$this->storedName=$storedName;$this->originalName=mb_substr($originalName,0,255);$this->mime=$mime;$this->size=$size;$this->sha256=$sha256;$this->uploadedBy=mb_substr($actor,0,180);$this->uploadedAt=new \DateTimeImmutable();
 }
 public function getId():?int{return $this->id;}
 public function getStoredName():string{return $this->storedName;}
 public function getOriginalName():string{return $this->originalName;}
 public function getMime():string{return $this->mime;}
 public function getSize():int{return $this->size;}
 public function getUploadedAt():\DateTimeImmutable{return $this->uploadedAt;}
}
