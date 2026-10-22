<?php
declare(strict_types=1);
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;
#[ORM\Entity]
#[ORM\Table(name:'compressor_fill_batch')]
class CompressorFillBatch {
 #[ORM\Id] #[ORM\GeneratedValue] #[ORM\Column] private ?int $id=null;
 #[ORM\ManyToOne(targetEntity: CompressorFillSession::class)] #[ORM\JoinColumn(nullable:false)] private CompressorFillSession $session;
 #[ORM\ManyToOne(targetEntity: User::class)] #[ORM\JoinColumn(nullable:false)] private User $recordedBy;
 #[ORM\Column] private int $endPressureBar;
 #[ORM\Column(type:'text',nullable:true)] private ?string $notes=null;
 #[ORM\Column] private \DateTimeImmutable $filledAt;
 public function __construct(CompressorFillSession $session,User $user,int $pressure,?string $notes){$this->session=$session;$this->recordedBy=$user;$this->endPressureBar=$pressure;$this->notes=$notes;$this->filledAt=new \DateTimeImmutable();}
 public function getId():?int{return $this->id;}
 public function getSession():CompressorFillSession{return $this->session;}
 public function getRecordedBy():User{return $this->recordedBy;}
 public function getEndPressureBar():int{return $this->endPressureBar;}
 public function getNotes():?string{return $this->notes;}
 public function getFilledAt():\DateTimeImmutable{return $this->filledAt;}
}
