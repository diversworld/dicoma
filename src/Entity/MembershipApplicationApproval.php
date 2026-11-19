<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'uniq_application_reviewer', columns: ['application_id', 'reviewer_id'])]
class MembershipApplicationApproval
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private MembershipApplication $application;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private User $reviewer;

    #[ORM\Column(length: 180)]
    private string $reviewerName;

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank, Assert\Length(max: 1000)]
    private string $note;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $approvedAt;

    public function __construct(MembershipApplication $application, User $reviewer, string $note)
    {
        $this->application = $application;
        $this->reviewer = $reviewer;
        $this->reviewerName = $reviewer->getUserIdentifier();
        $this->note = trim($note);
        $this->approvedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getApplication(): MembershipApplication { return $this->application; }
    public function getReviewer(): User { return $this->reviewer; }
    public function getReviewerName(): string { return $this->reviewerName; }
    public function getNote(): string { return $this->note; }
    public function getApprovedAt(): \DateTimeImmutable { return $this->approvedAt; }
}
