<?php
namespace App\Entity;

use App\Repository\ScheduleTemplateRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ScheduleTemplateRepository::class)]
#[ORM\Table(name: 'schedule_template')]
class ScheduleTemplate
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Club::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?Club $club = null;

    #[ORM\Column(length: 150)]
    #[Assert\NotBlank]
    private ?string $name = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    /** @var Collection<int, ScheduleTemplateEntry> */
    #[ORM\OneToMany(mappedBy: 'template', targetEntity: ScheduleTemplateEntry::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['dayOffset' => 'ASC', 'sequence' => 'ASC'])]
    private Collection $entries;

    public function __construct() { $this->entries = new ArrayCollection(); }
    public function __toString(): string { return $this->name ?? ''; }
    public function getId(): ?int { return $this->id; }
    public function getClub(): ?Club { return $this->club; }
    public function setClub(?Club $club): static { $this->club = $club; return $this; }
    public function getName(): ?string { return $this->name; }
    public function setName(?string $name): static { $this->name = $name; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description; return $this; }
    /** @return Collection<int, ScheduleTemplateEntry> */
    public function getEntries(): Collection { return $this->entries; }
    public function addEntry(ScheduleTemplateEntry $entry): static {
        if (!$this->entries->contains($entry)) { $this->entries->add($entry); $entry->setTemplate($this); }
        return $this;
    }
    public function removeEntry(ScheduleTemplateEntry $entry): static {
        if ($this->entries->removeElement($entry) && $entry->getTemplate() === $this) { $entry->setTemplate(null); }
        return $this;
    }
}
