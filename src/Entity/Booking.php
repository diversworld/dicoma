<?php

namespace App\Entity;

use App\Repository\BookingRepository;
use Doctrine\DBAL\Types\Types;
use App\Enum\BookingAttendanceStatus;
use App\Enum\CourseParticipantStatus;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[Assert\Callback('validateCourseParticipant')]
#[ORM\Table(name: 'booking',uniqueConstraints: [new ORM\UniqueConstraint(name: 'uniq_booking_schedule_member',columns: ['schedule_id', 'students_id'])])]
#[ORM\Entity(repositoryClass: BookingRepository::class)]
class Booking
{
	public function __construct()
	{
		$this->makeupBookings = new ArrayCollection();
	}
	
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $bookingdate = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $bookingnumber = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $status = null;

    #[ORM\ManyToOne(targetEntity: Schedule::class, inversedBy: 'bookings', cascade:["persist"])]
    private $schedule = null;

	#[ORM\ManyToOne(targetEntity: Member::class,inversedBy: 'bookings')]
	#[ORM\JoinColumn(name: 'students_id',referencedColumnName: 'id',nullable: true,onDelete: 'SET NULL')]
	private ?Member $member = null;

	#[ORM\Column(type: Types::STRING,length: 30,enumType: BookingAttendanceStatus::class,options: ['default' => 'planned'])]
	private BookingAttendanceStatus $attendanceStatus =	BookingAttendanceStatus::PLANNED;

	#[ORM\Column(type: Types::DATETIME_IMMUTABLE,nullable: true)]
	private ?\DateTimeImmutable $attendanceRecordedAt = null;

	#[ORM\Column(type: Types::TEXT,nullable: true)]
	private ?string $attendanceNotes = null;

	#[ORM\ManyToOne(targetEntity: self::class,inversedBy: 'makeupBookings')]
	#[ORM\JoinColumn(name: 'makeup_for_id',referencedColumnName: 'id',nullable: true,onDelete: 'SET NULL')]
	private ?self $makeupFor = null;

	/**
	 * @var Collection<int, Booking>
	 */
	#[ORM\OneToMany(mappedBy: 'makeupFor',targetEntity: self::class)]
	private Collection $makeupBookings;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    public function __toString()
    {
        return $this->bookingnumber ?? '';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBookingdate(): ?\DateTimeInterface
    {
        return $this->bookingdate;
    }

    public function getBookingnumber(): ?string
    {
        return $this->bookingnumber;
    }

    public function setBookingnumber(?string $bookingnumber): static
    {
        $this->bookingnumber = $bookingnumber;

        return $this;
    }

    public function setBookingdate(\DateTimeInterface $bookingdate): static
    {
        $this->bookingdate = $bookingdate;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): static
    {
        $this->status = $status;

        return $this;
    }

	public function getAttendanceStatus(): BookingAttendanceStatus
	{
		return $this->attendanceStatus;
	}

	public function setAttendanceStatus(
		BookingAttendanceStatus $attendanceStatus
	): static {
		$this->attendanceStatus = $attendanceStatus;

		if ($attendanceStatus === BookingAttendanceStatus::PLANNED) {
			$this->attendanceRecordedAt = null;
		} elseif ($this->attendanceRecordedAt === null) {
			$this->attendanceRecordedAt = new \DateTimeImmutable();
		}

		return $this;
	}

	public function getAttendanceStatusLabel(): string
	{
		return $this->attendanceStatus->label();
	}

	public function getAttendanceRecordedAt(): ?\DateTimeImmutable
	{
		return $this->attendanceRecordedAt;
	}

	public function setAttendanceRecordedAt(
		?\DateTimeImmutable $attendanceRecordedAt
	): static {
		$this->attendanceRecordedAt = $attendanceRecordedAt;

		return $this;
	}

	public function getAttendanceNotes(): ?string
	{
		return $this->attendanceNotes;
	}

	public function setAttendanceNotes(
		?string $attendanceNotes
	): static {
		$this->attendanceNotes = $attendanceNotes;

		return $this;
	}
	
	public function getMember(): ?Member
	{
		return $this->member;
	}

	public function setMember(?Member $member): static
	{
		$this->member = $member;

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

    public function getSchedule(): ?Schedule
    {
        return $this->schedule;
    }

    public function setSchedule(?Schedule $schedule): static
    {
        $this->schedule = $schedule;

        return $this;
    }

	public function getMakeupFor(): ?self
	{
		return $this->makeupFor;
	}

	public function setMakeupFor(?self $makeupFor): static
	{
		$this->makeupFor = $makeupFor;

		return $this;
	}

	/**
	 * @return Collection<int, Booking>
	 */
	public function getMakeupBookings(): Collection
	{
		return $this->makeupBookings;
	}

	public function addMakeupBooking(
		Booking $booking
	): static {
		if (!$this->makeupBookings->contains($booking)) {
			$this->makeupBookings->add($booking);
			$booking->setMakeupFor($this);
		}

		return $this;
	}

	public function removeMakeupBooking(
		Booking $booking
	): static {
		if ($this->makeupBookings->removeElement($booking)) {
			if ($booking->getMakeupFor() === $this) {
				$booking->setMakeupFor(null);
			}
		}

		return $this;
	}
	
	public function validateCourseParticipant(
		ExecutionContextInterface $context
	): void {
		if ($this->member === null || $this->schedule === null) {
			return;
		}

		$course = $this->schedule->getCourses();

		if ($course === null) {
			return;
		}

		foreach ($course->getParticipants() as $participant) {
			if (
				$participant->getMember() === $this->member
				&& $participant->getStatus()
					!== CourseParticipantStatus::CANCELLED
			) {
				return;
			}
		}

		$context
			->buildViolation(
				sprintf(
					'%s ist kein aktiver Teilnehmer des Kurses "%s".',
					$this->member->getFullName(),
					(string) $course
				)
			)
			->atPath('member')
			->addViolation();
	}

	public function isMakeupBooking(): bool
	{
		return $this->makeupFor !== null;
	}

	public function requiresMakeup(): bool
	{
		return $this->attendanceStatus
			=== BookingAttendanceStatus::MAKEUP_REQUIRED;
	}
}
