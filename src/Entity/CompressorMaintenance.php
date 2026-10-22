<?php
declare(strict_types=1);
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;
#[ORM\Entity]
#[ORM\Table(name:'compressor_maintenance')]
class CompressorMaintenance {
 #[ORM\Id] #[ORM\GeneratedValue] #[ORM\Column] private ?int $id=null;
 #[ORM\ManyToOne(targetEntity: Club::class)] #[ORM\JoinColumn(nullable:false)] private Club $club;
 #[ORM\ManyToOne(targetEntity: User::class)] #[ORM\JoinColumn(nullable:false)] private User $recordedBy;
 #[ORM\Column(length:100)] private string $compressor;
 #[ORM\Column(length:30)] private string $kind;
 #[ORM\Column(type:'text')] private string $description;
 #[ORM\Column] private \DateTimeImmutable $performedAt;
 #[ORM\Column(nullable:true)] private ?\DateTimeImmutable $dueAt=null;
 public function __construct(Club $club,User $user,string $compressor,string $kind,string $description,\DateTimeImmutable $performed,?\DateTimeImmutable $due){$this->club=$club;$this->recordedBy=$user;$this->compressor=$compressor;$this->kind=$kind;$this->description=$description;$this->performedAt=$performed;$this->dueAt=$due;}
 public function getId():?int{return $this->id;}
 public function getCompressor():string{return $this->compressor;}
 public function getKind():string{return $this->kind;}
 public function getDescription():string{return $this->description;}
 public function getPerformedAt():\DateTimeImmutable{return $this->performedAt;}
 public function getDueAt():?\DateTimeImmutable{return $this->dueAt;}
}
