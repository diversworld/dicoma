<?php
declare(strict_types=1);
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;
#[ORM\Entity]
#[ORM\Table(name: 'compressor_fill_session')]
class CompressorFillSession {
 #[ORM\Id] #[ORM\GeneratedValue] #[ORM\Column] private ?int $id=null;
 #[ORM\ManyToOne(targetEntity: Club::class)] #[ORM\JoinColumn(nullable:false)] private Club $club;
 #[ORM\ManyToOne(targetEntity: User::class)] #[ORM\JoinColumn(nullable:false)] private User $operator;
 #[ORM\Column(length:100)] private string $compressor;
 #[ORM\Column] private int $openingHoursTenths;
 #[ORM\Column] private bool $systemChecked=true;
 #[ORM\Column] private bool $filterChecked=true;
 #[ORM\Column] private bool $airStatusChecked=true;
 #[ORM\Column(type:'text',nullable:true)] private ?string $notes=null;
 #[ORM\Column] private \DateTimeImmutable $openedAt;
 #[ORM\Column(nullable:true)] private ?\DateTimeImmutable $closedAt=null;
 #[ORM\Column(nullable:true)] private ?int $closingHoursTenths=null;
 public function __construct(Club $club,User $user,string $compressor,int $hours,?string $notes){$this->club=$club;$this->operator=$user;$this->compressor=$compressor;$this->openingHoursTenths=$hours;$this->notes=$notes;$this->openedAt=new \DateTimeImmutable();}
 public function getId():?int{return $this->id;}
 public function getClub():Club{return $this->club;}
 public function getOperator():User{return $this->operator;}
 public function getCompressor():string{return $this->compressor;}
 public function getOpeningHoursTenths():int{return $this->openingHoursTenths;}
 public function getNotes():?string{return $this->notes;}
 public function getOpenedAt():\DateTimeImmutable{return $this->openedAt;}
 public function getClosedAt():?\DateTimeImmutable{return $this->closedAt;}
 public function getClosingHoursTenths():?int{return $this->closingHoursTenths;}
 public function close(int $hours):void{$this->closingHoursTenths=$hours;$this->closedAt=new \DateTimeImmutable();}
}
