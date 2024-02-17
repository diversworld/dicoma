<?php

namespace App\Entity;

use App\Form\ScheduleType;
use App\Repository\BookingRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BookingRepository::class)]
class Booking
{
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

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'bookings', cascade:["persist"])]
    private $students;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    public function __toString()
    {
        return $this->bookingnumber;
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

    public function getStudents(): ?User
    {
        return $this->students;
    }

    public function setStudents(?User $students): static
    {
        $this->students = $students;

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


}