<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'bar_shift_checklist')]
#[ORM\UniqueConstraint(name: 'uniq_bar_shift_checklist', columns: ['shift_id'])]
#[ORM\Index(name: 'IDX_61BA675885ECDE76', columns: ['completed_by_id'])]
class BarShiftChecklist
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\OneToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private BarShift $shift;
    #[ORM\Column(type: 'json')]
    private array $items = [];
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $handover = null;
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Member $completedBy = null;

    public function __construct(BarShift $shift)
    {
        $this->shift = $shift;
        $this->items = array_map(
            static fn (string $label): array => ['label' => $label, 'done' => false, 'note' => ''],
            ['Eingangsbereich und Theke kontrolliert', 'Kühlschrank- und Getränkestand dokumentiert', 'Sauberkeit und Müllentsorgung geprüft', 'Besondere Vorkommnisse festgehalten', 'Kasse und Schlüssel übergeben'],
        );
    }

    public function getId(): ?int { return $this->id; }
    public function getShift(): BarShift { return $this->shift; }
    public function getItems(): array { return $this->items; }
    public function getHandover(): ?string { return $this->handover; }
    public function getCompletedAt(): ?\DateTimeImmutable { return $this->completedAt; }
    public function getCompletedBy(): ?Member { return $this->completedBy; }

    public function submit(array $checks, array $notes, string $handover, Member $member): void
    {
        $handover = trim($handover);
        if (mb_strlen($handover) > 10000) {
            throw new \DomainException('Übergabe darf höchstens 10000 Zeichen lang sein.');
        }
        foreach ($this->items as $index => &$item) {
            $key = (string) $index;
            $item['done'] = isset($checks[$key]) && in_array((string) $checks[$key], ['1', 'on'], true);
            $note = trim((string) ($notes[$key] ?? ''));
            if (mb_strlen($note) > 1000) {
                throw new \DomainException('Checklistenbemerkung darf höchstens 1000 Zeichen lang sein.');
            }
            $item['note'] = $note;
        }
        unset($item);
        $this->handover = $handover === '' ? null : $handover;
        $this->completedBy = $member;
        $this->completedAt = new \DateTimeImmutable();
    }
}
