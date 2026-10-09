<?php

namespace App\Entity;

use App\Repository\ScheduleRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
<<<<<<< HEAD
use App\Enum\BookingAttendanceStatus;

#[ORM\Table(name: 'schedule',uniqueConstraints: [new ORM\UniqueConstraint(name: 'uniq_schedule_course_training_sequence',columns: ['courses_id','training_unit_type_id','training_unit_sequence'])])]
=======

>>>>>>> origin/main
#[ORM\Entity(repositoryClass: ScheduleRepository::class)]
class Schedule
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image = null;

<<<<<<< HEAD
    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $startDate = null;

    #[ORM\Column(nullable: true)]
	private ?int $duration = null;

    #[ORM\Column(type: Types::TIME_MUTABLE, nullable: true)]
=======
    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $startDate = null;

    #[ORM\Column]
    private ?int $duration = null;

    #[ORM\Column(type: Types::TIME_MUTABLE)]
>>>>>>> origin/main
    private ?\DateTimeInterface $startTime = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $location = null;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $locationStreet = null;

<<<<<<< HEAD
	#[ORM\Column(length: 10,nullable: true)]
	private ?string $locationPostal = null;
=======
    #[ORM\Column(nullable: true)]
    private ?int $locationPostal = null;
>>>>>>> origin/main

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $locationCity = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\ManyToOne(inversedBy: 'schedule')]
    private ?Courses $courses = null;

    #[ORM\OneToMany(mappedBy: 'schedule', targetEntity: Booking::class)]
    private Collection $bookings;

<<<<<<< HEAD
	#[ORM\Column(type: Types::DECIMAL,precision: 10,scale: 2,nullable: true)]
	private ?string $price = null;

	#[ORM\ManyToOne]
	#[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
	private ?TrainingUnitType $trainingUnitType = null;

	#[ORM\Column(nullable: true)]
	private ?int $trainingUnitSequence = null;
	
=======
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?float $price = null;

>>>>>>> origin/main
    #[ORM\ManyToOne(inversedBy: 'schedules')]
    private ?Member $instructor = null;

    public function __construct()
    {
        $this->bookings = new ArrayCollection();
    }

    public function __toString()
    {
<<<<<<< HEAD
		return $this->title ?? '';
=======
        return $this->title;
>>>>>>> origin/main
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getStartDate(): ?\DateTimeInterface
    {
        return $this->startDate;
    }

<<<<<<< HEAD
	public function setStartDate(
		?\DateTimeInterface $startDate
	): static {
		$this->startDate = $startDate;

		return $this;
	}
=======
    public function setStartDate(\DateTimeInterface $startDate): static
    {
        $this->startDate = $startDate;

        return $this;
    }
>>>>>>> origin/main

    public function getDuration(): ?int
    {
        return $this->duration;
    }

<<<<<<< HEAD
	public function setDuration(?int $duration): static
	{
		$this->duration = $duration;

		return $this;
	}
=======
    public function setDuration(int $duration): static
    {
        $this->duration = $duration;

        return $this;
    }
>>>>>>> origin/main

    public function getStartTime(): ?\DateTimeInterface
    {
        return $this->startTime;
    }

<<<<<<< HEAD
	public function setStartTime(?\DateTimeInterface $startTime): static {
		$this->startTime = $startTime;

		return $this;
	}

	public function getLocationPostal(): ?string
	{
		return $this->locationPostal;
	}

	public function setLocationPostal(
		?string $locationPostal
	): static {
		$this->locationPostal = $locationPostal;

		return $this;
	}

	public function getLocation(): ?string
=======
    public function setStartTime(\DateTimeInterface $startTime): static
    {
        $this->startTime = $startTime;

        return $this;
    }

    public function getLocation(): ?string
>>>>>>> origin/main
    {
        return $this->location;
    }

    public function setLocation(?string $location): static
    {
        $this->location = $location;

        return $this;
    }
<<<<<<< HEAD
	
=======

>>>>>>> origin/main
    public function getLocationStreet(): ?string
    {
        return $this->locationStreet;
    }

    public function setLocationStreet(?string $locationStreet): static
    {
        $this->locationStreet = $locationStreet;

        return $this;
    }

<<<<<<< HEAD
=======
    public function getLocationPostal(): ?int
    {
        return $this->locationPostal;
    }

    public function setLocationPostal(?int $locationPostal): static
    {
        $this->locationPostal = $locationPostal;

        return $this;
    }

>>>>>>> origin/main
    public function getLocationCity(): ?string
    {
        return $this->locationCity;
    }

    public function setLocationCity(?string $locationCity): static
    {
        $this->locationCity = $locationCity;

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

    public function getCourses(): ?Courses
    {
        return $this->courses;
    }

    public function setCourses(?Courses $courses): static
    {
        $this->courses = $courses;

        return $this;
    }

    public function getInstructor(): ?Member
    {
        return $this->instructor;
    }

    public function setInstructor(?Member $instructor): static
    {
        $this->instructor = $instructor;

        return $this;
    }

<<<<<<< HEAD
	public function getTrainingUnitType(): ?TrainingUnitType
	{
		return $this->trainingUnitType;
	}

	public function setTrainingUnitType(
		?TrainingUnitType $trainingUnitType
	): static {
		$this->trainingUnitType = $trainingUnitType;

		return $this;
	}
	
=======
>>>>>>> origin/main
    /**
     * @return Collection<int, Booking>
     */
    public function getBookings(): Collection
    {
        return $this->bookings;
    }

    public function addBooking(Booking $booking): static
    {
        if (!$this->bookings->contains($booking)) {
            $this->bookings->add($booking);
            $booking->setSchedule($this);
        }

        return $this;
    }

    public function removeBooking(Booking $booking): static
    {
        if ($this->bookings->removeElement($booking)) {
            // set the owning side to null (unless already changed)
            if ($booking->getSchedule() === $this) {
                $booking->setSchedule(null);
            }
        }

        return $this;
    }
<<<<<<< HEAD
	
	public function getDurationHours(): ?float
	{
		return $this->duration !== null
			? $this->duration / 60
			: null;
	}

	public function setDurationHours(?float $hours): static
	{
		$this->duration = $hours !== null
			? (int) round($hours * 60)
			: null;

		return $this;
	}

	public function getDurationLabel(): string
	{
		if ($this->duration === null) {
			return '—';
		}

		$hours = intdiv($this->duration, 60);
		$minutes = $this->duration % 60;

		return sprintf('%d Std. %02d Min.', $hours, $minutes);
	}
	
=======

>>>>>>> origin/main
    public function getPrice(): ?string
    {
        return $this->price;
    }

<<<<<<< HEAD
	public function setPrice(?string $price): static
	{
		$this->price = $price;

		return $this;
	}
=======
    public function setPrice(string $price): static
    {
        $this->price = $price;

        return $this;
    }
>>>>>>> origin/main

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): static
    {
        $this->image = $image;

        return $this;
    }
<<<<<<< HEAD
	
	public function getTrainingUnitSequence(): ?int
	{
		return $this->trainingUnitSequence;
	}

	public function setTrainingUnitSequence(
		?int $trainingUnitSequence
	): static {
		$this->trainingUnitSequence = $trainingUnitSequence;

		return $this;
	}
	
	public function getBookingCount(): int
	{
		return $this->bookings->count();
	}

	public function getPresentCount(): int
	{
		return $this->countBookingsByAttendanceStatus(
			BookingAttendanceStatus::PRESENT
		);
	}

	public function getExcusedCount(): int
	{
		return $this->countBookingsByAttendanceStatus(
			BookingAttendanceStatus::EXCUSED
		);
	}

	public function getAbsentCount(): int
	{
		return $this->countBookingsByAttendanceStatus(
			BookingAttendanceStatus::ABSENT
		);
	}

	public function getMakeupRequiredCount(): int
	{
		return $this->countBookingsByAttendanceStatus(
			BookingAttendanceStatus::MAKEUP_REQUIRED
		);
	}

	public function getPlannedCount(): int
	{
		return $this->countBookingsByAttendanceStatus(
			BookingAttendanceStatus::PLANNED
		);
	}

	private function countBookingsByAttendanceStatus(
		BookingAttendanceStatus $status
	): int {
		$count = 0;

		foreach ($this->bookings as $booking) {
			if (
				$booking->getAttendanceStatus()
				=== $status
			) {
				++$count;
			}
		}

		return $count;
	}
	
	public function getAttendanceSummary(): string
	{
		$total = $this->getBookingCount();

		if ($total === 0) {
			return 'Keine Teilnehmer';
		}

		$present = $this->getPresentCount();
		$planned = $this->getPlannedCount();

		if ($planned === $total) {
			return sprintf(
				'%d Teilnehmer · noch nicht erfasst',
				$total
			);
		}

		return sprintf(
			'%d/%d anwesend',
			$present,
			$total
		);
	}

	public function getEndTime(): ?\DateTimeImmutable
	{
		if (
			$this->startTime === null
			|| $this->duration === null
		) {
			return null;
		}

		$start = \DateTimeImmutable::createFromInterface(
			$this->startTime
		);

		return $start->modify(
			sprintf('+%d minutes', $this->duration)
		);
	}

	public function getEndTimeLabel(): string
	{
		return $this->getEndTime()?->format('H:i') ?? '—';
	}

	public function getTimeRange(): string
	{
		if ($this->startTime === null) {
			return 'Noch nicht geplant';
		}

		$start = $this->startTime->format('H:i');
		$end = $this->getEndTime();

		if ($end === null) {
			return $start . ' Uhr';
		}

		return sprintf(
			'%s – %s Uhr',
			$start,
			$end->format('H:i')
		);
	}
	
	public function getTrainingUnitLabel(): string
	{
		$type = $this->trainingUnitType;

		if ($type === null) {
			return '';
		}

		$name = $type->getName() ?? '';

		if ($this->trainingUnitSequence === null) {
			return $name;
		}

		return sprintf(
			'%s %d',
			$name,
			$this->trainingUnitSequence
		);
	}
=======
>>>>>>> origin/main
}
