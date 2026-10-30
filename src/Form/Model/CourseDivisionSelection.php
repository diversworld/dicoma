<?php

declare(strict_types=1);

namespace App\Form\Model;

use App\Entity\Courses;
use App\Entity\Sport;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[Assert\Callback('validate')]
final class CourseDivisionSelection
{
    #[Assert\NotBlank]
    public ?string $scope;

    /** @var Collection<int, Sport> */
    public Collection $divisions;

    public function __construct(Courses $course)
    {
        $this->divisions = new ArrayCollection($course->getDivisions()->toArray());
        $this->scope = !$course->isDivisionReviewed() ? 'unreviewed' : ($this->divisions->isEmpty() ? 'club' : 'divisions');
    }

    public function validate(ExecutionContextInterface $context): void
    {
        if ($this->scope === 'divisions' && $this->divisions->isEmpty()) {
            $context->buildViolation('Bitte mindestens eine Sparte auswählen.')->atPath('divisions')->addViolation();
        } elseif ($this->scope !== 'divisions' && !$this->divisions->isEmpty()) {
            $context->buildViolation('Für diese Auswahl dürfen keine Sparten markiert sein.')->atPath('divisions')->addViolation();
        }
    }
}
