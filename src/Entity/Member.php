<?php

namespace App\Entity;

use App\Enum\MembershipStatus;
use App\Repository\MemberRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[Assert\Callback('validateSportClubs')]
#[ORM\Table(
    name: 'member',
    uniqueConstraints: [
        new ORM\UniqueConstraint(
            name: 'uniq_member_club_number',
            columns: ['club_id', 'member_number']
        )
    ]
)]
#[ORM\Entity(repositoryClass: MemberRepository::class)]
class Member
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(
        targetEntity: Club::class,
        inversedBy: 'members'
    )]
    #[ORM\JoinColumn(nullable: true)]
    private ?Club $club = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $memberNumber = null;

    #[ORM\Column(
        type: Types::DATE_MUTABLE,
        nullable: true
    )]
    private ?\DateTimeInterface $joinedAt = null;

    #[ORM\Column(
        type: Types::DATE_MUTABLE,
        nullable: true
    )]
    private ?\DateTimeInterface $leftAt = null;

    #[ORM\Column(
        type: Types::STRING,
        length: 20,
        enumType: MembershipStatus::class,
        options: ['default' => 'active']
    )]
    private MembershipStatus $membershipStatus =
        MembershipStatus::ACTIVE;

    #[ORM\Column(length: 100)]
    private ?string $firstname = null;

    #[ORM\Column(length: 100)]
    private ?string $lastname = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $birthday = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(length: 25, nullable: true)]
    private ?string $mobile = null;

    #[ORM\Column(length: 25, nullable: true)]
    private ?string $phone = null;

    /**
     * @var Collection<int, MemberSport>
     */
    #[ORM\OneToMany(
        mappedBy: 'member',
        targetEntity: MemberSport::class,
        cascade: ['persist'],
        orphanRemoval: true
    )]
    private Collection $sports;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $street = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $postalCode = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $city = null;

    #[ORM\Column(
        type: Types::TEXT,
        nullable: true
    )]
    private ?string $notes = null;

    #[ORM\OneToOne(
        inversedBy: 'member',
        cascade: ['persist', 'remove']
    )]
    private ?User $user = null;

    /**
     * @deprecated
     *
     * Legacy-Feld des ursprünglichen DiveCourseManagers.
     * Wird durch Mitgliedschaft, Sportarten und
     * Qualifikationen ersetzt.
     */
    #[ORM\Column(length: 50, nullable: true)]
    private ?string $category = null;

    #[ORM\Column(nullable: true)]
    private ?bool $published = null;

    /**
     * Termine, bei denen dieses Mitglied als Ausbilder
     * eingetragen ist.
     *
     * @var Collection<int, Schedule>
     */
    #[ORM\OneToMany(
        mappedBy: 'instructor',
        targetEntity: Schedule::class
    )]
    private Collection $schedules;

    /**
     * Terminbuchungen dieses Mitglieds.
     *
     * Die zugehörige Booking-Entity verwendet künftig
     * die Property "member".
     *
     * @var Collection<int, Booking>
     */
    #[ORM\OneToMany(
        mappedBy: 'member',
        targetEntity: Booking::class
    )]
    private Collection $bookings;

    /**
     * @deprecated
     *
     * Legacy-Feld des ursprünglichen DiveCourseManagers.
     */
    #[ORM\Column(
        type: Types::STRING,
        length: 255,
        nullable: true
    )]
    private ?string $status = null;

    /**
     * @var Collection<int, MemberQualification>
     */
    #[ORM\OneToMany(
        mappedBy: 'member',
        targetEntity: MemberQualification::class,
        cascade: ['persist'],
        orphanRemoval: true
    )]
    private Collection $qualifications;

    /**
     * Legacy-/Formularfeld für Kennwortänderungen.
     */
    private $oldPassword;

    /**
     * Legacy-/Formularfeld für Kennwortänderungen.
     */
    #[Assert\Length(
        min: 6,
        minMessage: 'Password should by at least 6 chars long'
    )]
    private $plainPassword;

    public function __construct()
    {
        $this->schedules = new ArrayCollection();
        $this->bookings = new ArrayCollection();
        $this->sports = new ArrayCollection();
        $this->qualifications = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->getFullName();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getClub(): ?Club
    {
        return $this->club;
    }

    public function setClub(?Club $club): static
    {
        $this->club = $club;

        return $this;
    }

    public function getMemberNumber(): ?string
    {
        return $this->memberNumber;
    }

    public function setMemberNumber(
        ?string $memberNumber
    ): static {
        $this->memberNumber = $memberNumber !== null
            ? trim($memberNumber)
            : null;

        return $this;
    }

    public function getJoinedAt(): ?\DateTimeInterface
    {
        return $this->joinedAt;
    }

    public function setJoinedAt(
        ?\DateTimeInterface $joinedAt
    ): static {
        $this->joinedAt = $joinedAt;

        return $this;
    }

    public function getLeftAt(): ?\DateTimeInterface
    {
        return $this->leftAt;
    }

    public function setLeftAt(
        ?\DateTimeInterface $leftAt
    ): static {
        $this->leftAt = $leftAt;

        return $this;
    }

    public function getMembershipStatus(): MembershipStatus
    {
        return $this->membershipStatus;
    }

    public function setMembershipStatus(
        MembershipStatus $membershipStatus
    ): static {
        $this->membershipStatus = $membershipStatus;

        return $this;
    }

    public function getFirstname(): ?string
    {
        return $this->firstname;
    }

    public function setFirstname(
        string $firstname
    ): static {
        $this->firstname = $firstname;

        return $this;
    }

    public function getLastname(): ?string
    {
        return $this->lastname;
    }

    public function setLastname(
        string $lastname
    ): static {
        $this->lastname = $lastname;

        return $this;
    }

    public function getFullName(): string
    {
        return trim(
            sprintf(
                '%s %s',
                $this->firstname ?? '',
                $this->lastname ?? ''
            )
        );
    }

    public function getBirthday(): ?\DateTimeInterface
    {
        return $this->birthday;
    }

    public function setBirthday(
        \DateTimeInterface $birthday
    ): static {
        $this->birthday = $birthday;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getMobile(): ?string
    {
        return $this->mobile;
    }

    public function setMobile(?string $mobile): static
    {
        $this->mobile = $mobile;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;

        return $this;
    }

    public function getStreet(): ?string
    {
        return $this->street;
    }

    public function setStreet(?string $street): static
    {
        $this->street = $street;

        return $this;
    }

    public function getPostalCode(): ?string
    {
        return $this->postalCode;
    }

    public function setPostalCode(
        ?string $postalCode
    ): static {
        $this->postalCode = $postalCode !== null
            ? trim($postalCode)
            : null;

        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(?string $city): static
    {
        $this->city = $city;

        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    /**
     * @deprecated
     */
    public function getCategory(): ?string
    {
        return $this->category;
    }

    /**
     * @deprecated
     */
    public function setCategory(
        ?string $category
    ): static {
        $this->category = $category;

        return $this;
    }

    public function isPublished(): ?bool
    {
        return $this->published;
    }

    public function setPublished(
        ?bool $published
    ): static {
        $this->published = $published;

        return $this;
    }

    public function isActiveMember(): bool
    {
        return $this->membershipStatus
                === MembershipStatus::ACTIVE
            && $this->leftAt === null;
    }

    /**
     * @return Collection<int, MemberSport>
     */
    public function getSports(): Collection
    {
        return $this->sports;
    }

    public function addSport(
        MemberSport $memberSport
    ): static {
        if (!$this->sports->contains($memberSport)) {
            $this->sports->add($memberSport);
            $memberSport->setMember($this);
        }

        return $this;
    }

    public function removeSport(
        MemberSport $memberSport
    ): static {
        if ($this->sports->removeElement($memberSport)) {
            if ($memberSport->getMember() === $this) {
                $memberSport->setMember(null);
            }
        }

        return $this;
    }

    public function getSportNames(): string
    {
        $names = [];

        foreach ($this->sports as $memberSport) {
            $sport = $memberSport->getSport();

            if ($sport === null) {
                continue;
            }

            $names[] = $sport->getShortName()
                ?: $sport->getName();
        }

        return implode(', ', $names);
    }

    public function validateSportClubs(
        ExecutionContextInterface $context
    ): void {
        if ($this->club === null) {
            return;
        }

        foreach ($this->sports as $memberSport) {
            $sport = $memberSport->getSport();

            if (
                $sport === null
                || $sport->getClub() === null
            ) {
                continue;
            }

            if (
                $sport->getClub()->getId()
                !== $this->club->getId()
            ) {
                $context
                    ->buildViolation(
                        sprintf(
                            'Die Sportart "%s" gehört nicht '
                            . 'zum Verein "%s".',
                            $sport->getName(),
                            $this->club->getName()
                        )
                    )
                    ->atPath('sports')
                    ->addViolation();
            }
        }
    }

    /**
     * @return Collection<int, Schedule>
     */
    public function getSchedules(): Collection
    {
        return $this->schedules;
    }

    public function addSchedule(
        Schedule $schedule
    ): static {
        if (!$this->schedules->contains($schedule)) {
            $this->schedules->add($schedule);
            $schedule->setInstructor($this);
        }

        return $this;
    }

    public function removeSchedule(
        Schedule $schedule
    ): static {
        if ($this->schedules->removeElement($schedule)) {
            if ($schedule->getInstructor() === $this) {
                $schedule->setInstructor(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Booking>
     */
    public function getBookings(): Collection
    {
        return $this->bookings;
    }

    public function addBooking(
        Booking $booking
    ): static {
        if (!$this->bookings->contains($booking)) {
            $this->bookings->add($booking);
            $booking->setMember($this);
        }

        return $this;
    }

    public function removeBooking(
        Booking $booking
    ): static {
        if ($this->bookings->removeElement($booking)) {
            if ($booking->getMember() === $this) {
                $booking->setMember(null);
            }
        }

        return $this;
    }

    /**
     * @deprecated
     */
    public function getStatus(): ?string
    {
        return $this->status;
    }

    /**
     * @deprecated
     */
    public function setStatus(?string $status): static
    {
        $this->status = $status;

        return $this;
    }

    /**
     * @return Collection<int, MemberQualification>
     */
    public function getQualifications(): Collection
    {
        return $this->qualifications;
    }

    public function addQualification(
        MemberQualification $qualification
    ): static {
        if (!$this->qualifications->contains($qualification)) {
            $this->qualifications->add($qualification);
            $qualification->setMember($this);
        }

        return $this;
    }

    public function removeQualification(
        MemberQualification $qualification
    ): static {
        if (
            $this->qualifications
                ->removeElement($qualification)
        ) {
            if ($qualification->getMember() === $this) {
                $qualification->setMember(null);
            }
        }

        return $this;
    }

    public function getQualificationNames(): string
    {
        $names = [];

        foreach (
            $this->qualifications
            as $memberQualification
        ) {
            $qualification =
                $memberQualification->getQualification();

            if ($qualification !== null) {
                $names[] =
                    $qualification->getShortName()
                    ?: $qualification->getName();
            }
        }

        return implode(', ', $names);
    }

    public function getOldPassword(): ?string
    {
        return $this->oldPassword;
    }

    public function setOldPassword(
        ?string $oldPassword
    ): static {
        $this->oldPassword = $oldPassword;

        return $this;
    }

    public function getPlainPassword(): ?string
    {
        return $this->plainPassword;
    }

    public function setPlainPassword(
        ?string $plainPassword
    ): static {
        $this->plainPassword = $plainPassword;

        return $this;
    }
}