<?php
declare(strict_types=1);
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;
#[ORM\Entity]
#[ORM\Table(name:'compressor_fill_cylinder')]
class CompressorFillCylinder {
 #[ORM\Id] #[ORM\GeneratedValue] #[ORM\Column] private ?int $id=null;
 #[ORM\ManyToOne(targetEntity: CompressorFillBatch::class)] #[ORM\JoinColumn(nullable:false)] private CompressorFillBatch $batch;
 #[ORM\ManyToOne(targetEntity: Tank::class)] #[ORM\JoinColumn(nullable:true,onDelete:'SET NULL')] private ?Tank $tank;
 #[ORM\Column(length:100)] private string $identification;
 #[ORM\Column] private bool $cylinderChecked=true;
 #[ORM\Column(type:"decimal",precision:5,scale:1,nullable:true)] private ?string $volumeLiters=null;
 public function __construct(CompressorFillBatch $batch,?Tank $tank,string $id,string $volumeLiters){$this->batch=$batch;$this->tank=$tank;$this->identification=$id;$this->volumeLiters=$volumeLiters;}
 public function getId():?int{return $this->id;}
 public function getBatch():CompressorFillBatch{return $this->batch;}
 public function getTank():?Tank{return $this->tank;}
 public function getIdentification():string{return $this->identification;}
 public function getVolumeLiters():?string{return $this->volumeLiters;}
}
