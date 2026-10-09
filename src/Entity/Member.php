<?php

namespace App\Entity;

<<<<<<< HEAD
use App\Enum\MembershipStatus;
=======
>>>>>>> origin/main
use App\Repository\MemberRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
<<<<<<< HEAD
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
=======

>>>>>>> origin/main
#[ORM\Entity(repositoryClass: MemberRepository::class)]
class Member
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

<<<<<<< HEAD
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

=======
>>>>>>> origin/main
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

<<<<<<< HEAD
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
=======
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $street = null;

    #[ORM\Column(nullable: true)]
    private ?int $postal = null;
>>>>>>> origin/main

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $city = null;

<<<<<<< HEAD
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
=======
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\OneToOne(inversedBy: 'member', cascade: ['persist', 'remove'])]
    private ?User $user = null;

    #[ORM\Column(length: 20)]
>>>>>>> origin/main
    private ?string $category = null;

    #[ORM\Column(nullable: true)]
    private ?bool $published = null;

<<<<<<< HEAD
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

=======
    #[ORM\OneToMany(mappedBy: 'instructor', targetEntity: Schedule::class)]
    private Collection $schedules;

    #[ORM\OneToMany(mappedBy: 'students', targetEntity: Booking::class)]
    private Collection $bookings;

    #[ORM\Column(type: "string", length: 255, nullable: true)]
    private ?string $status = null;

>>>>>>> origin/main
    public function __construct()
    {
        $this->schedules = new ArrayCollection();
        $this->bookings = new ArrayCollection();
<<<<<<< HEAD
        $this->sports = new ArrayCollection();
        $this->qualifications = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->getFullName();
=======
    }
    /**
     * @SecurityAssert\UserPassword(
     *     message = "Wrong value for your current password"
     * )
     */
    private $oldPassword;

    /**
     * @Assert\Length(
     *     min = 6,
     *     minMessage = "Password should by at least 6 chars long"
     * )
     */
    private $plainPassword;

    public function __tostring()
    {
        return $this->firstname . " " . $this->lastname;
>>>>>>> origin/main
    }

    public function getId(): ?int
    {
        return $this->id;
    }

<<<<<<< HEAD
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

=======
    public function getOldPassword(): string
    {
        return $this->oldPassword;
    }

    public function setOldPassword(string $oldPassword): self
    {
        $this->oldPassword = $oldPassword;
        return $this;
    }

    public function getPlainPassword(): string
    {
        return $this->plainPassword;
    }

    public function setPlainPassword(string $password): self
    {
        $this->plainPassword = $password;
>>>>>>> origin/main
        return $this;
    }

    public function getFirstname(): ?string
    {
        return $this->firstname;
    }

<<<<<<< HEAD
    public function setFirstname(
        string $firstname
    ): static {
=======
    public function setFirstname(string $firstname): static
    {
>>>>>>> origin/main
        $this->firstname = $firstname;

        return $this;
    }

    public function getLastname(): ?string
    {
        return $this->lastname;
    }

<<<<<<< HEAD
    public function setLastname(
        string $lastname
    ): static {
=======
    public function setLastname(string $lastname): static
    {
>>>>>>> origin/main
        $this->lastname = $lastname;

        return $this;
    }

<<<<<<< HEAD
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

=======
>>>>>>> origin/main
    public function getBirthday(): ?\DateTimeInterface
    {
        return $this->birthday;
    }

<<<<<<< HEAD
    public function setBirthday(
        \DateTimeInterface $birthday
    ): static {
=======
    public function setBirthday(\DateTimeInterface $birthday): static
    {
>>>>>>> origin/main
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

<<<<<<< HEAD
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
=======
    public function getPostal(): ?int
    {
        return $this->postal;
    }

    public function setPostal(?int $postal): static
    {
        $this->postal = $postal;
>>>>>>> origin/main

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

<<<<<<< HEAD
    /**
     * @deprecated
     */
=======
>>>>>>> origin/main
    public function getCategory(): ?string
    {
        return $this->category;
    }

<<<<<<< HEAD
    /**
     * @deprecated
     */
    public function setCategory(
        ?string $category
    ): static {
=======
    public function setCategory(string $category): static
    {
>>>>>>> origin/main
        $this->category = $category;

        return $this;
    }

    public function isPublished(): ?bool
    {
        return $this->published;
    }

<<<<<<< HEAD
    public function setPublished(
        ?bool $published
    ): static {
=======
    public function setPublished(?bool $published): static
    {
>>>>>>> origin/main
        $this->published = $published;

        return $this;
    }

<<<<<<< HEAD
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

=======
>>>>>>> origin/main
    /**
     * @return Collection<int, Schedule>
     */
    public function getSchedules(): Collection
    {
        return $this->schedules;
    }

<<<<<<< HEAD
    public function addSchedule(
        Schedule $schedule
    ): static {
=======
    public function addSchedule(Schedule $schedule): static
    {
>>>>>>> origin/main
        if (!$this->schedules->contains($schedule)) {
            $this->schedules->add($schedule);
            $schedule->setInstructor($this);
        }

        return $this;
    }

<<<<<<< HEAD
    public function removeSchedule(
        Schedule $schedule
    ): static {
        if ($this->schedules->removeElement($schedule)) {
=======
    public function removeSchedule(Schedule $schedule): static
    {
        if ($this->schedules->removeElement($schedule)) {
            // set the owning side to null (unless already changed)
>>>>>>> origin/main
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

<<<<<<< HEAD
    public function addBooking(
        Booking $booking
    ): static {
        if (!$this->bookings->contains($booking)) {
            $this->bookings->add($booking);
            $booking->setMember($this);
=======
    public function addBooking(Booking $booking): static
    {
        if (!$this->bookings->contains($booking)) {
            $this->bookings->add($booking);
            $booking->setStudents($this);
>>>>>>> origin/main
        }

        return $this;
    }

<<<<<<< HEAD
    public function removeBooking(
        Booking $booking
    ): static {
        if ($this->bookings->removeElement($booking)) {
            if ($booking->getMember() === $this) {
                $booking->setMember(null);
=======
    public function removeBooking(Booking $booking): static
    {
        if ($this->bookings->removeElement($booking)) {
            // set the owning side to null (unless already changed)
            if ($booking->getStudents() === $this) {
                $booking->setStudents(null);
>>>>>>> origin/main
            }
        }

        return $this;
    }

<<<<<<< HEAD
    /**
     * @deprecated
     */
=======
>>>>>>> origin/main
    public function getStatus(): ?string
    {
        return $this->status;
    }

<<<<<<< HEAD
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
=======
    public function setStatus(?string $status): self
    {
        $this->status = $status;
        return $this;
    }
}
>>>>>>> origin/main
