<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\Club;
use App\Entity\Member;
use App\Entity\MemberSport;
use App\Entity\Sport;
use App\Entity\Team;
use App\Entity\TeamMember;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class TeamMemberAssignmentValidationTest extends TestCase
{
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        $this->validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();
    }

    /** @return array{TeamMember, Member, Sport, Club} */
    private function assignment(): array
    {
        $club = new Club();
        $sport = (new Sport())->setClub($club);
        $team = (new Team())->setSport($sport);
        $member = (new Member())->setClub($club);
        $assignment = (new TeamMember())->setTeam($team)->setMember($member);

        return [$assignment, $member, $sport, $club];
    }

    private function validateAssignment(TeamMember $assignment): ConstraintViolationListInterface
    {
        return $this->validator->validate($assignment, new Callback('validateAssignment'));
    }

    public function testMissingSportWithoutConsentIsRejected(): void
    {
        [$assignment] = $this->assignment();
        $violations = $this->validateAssignment($assignment);
        self::assertGreaterThan(0, $violations->count());
        self::assertSame('member', $violations->get(0)->getPropertyPath());
    }

    public function testMissingSportWithConsentIsAllowedAtEntityValidation(): void
    {
        [$assignment] = $this->assignment();
        $assignment->setAssignMissingSport(true);
        self::assertCount(0, $this->validateAssignment($assignment));
    }

    public function testExistingSportNeedsNoConsent(): void
    {
        [$assignment, $member, $sport] = $this->assignment();
        $member->addSport((new MemberSport())->setSport($sport));
        self::assertCount(0, $this->validateAssignment($assignment));
    }

    public function testDifferentClubIsRejectedEvenWithConsent(): void
    {
        [$assignment, $member] = $this->assignment();
        $member->setClub(new Club());
        $assignment->setAssignMissingSport(true);
        self::assertGreaterThan(0, $this->validateAssignment($assignment)->count());
    }

    public function testMissingTeamOrMemberDoesNotTriggerSportCallback(): void
    {
        self::assertCount(0, $this->validateAssignment(new TeamMember()));
    }

    public function testConsentIsOffByDefault(): void
    {
        self::assertFalse((new TeamMember())->isAssignMissingSport());
    }

    public function testAssignmentDatesAreInclusiveAndRejectInvertedPeriods(): void
    {
        [$assignment] = $this->assignment();
        $assignment->setAssignMissingSport(true);
        $assignment->setJoinedAt(new \DateTime('2026-10-10'))->setLeftAt(new \DateTime('2026-10-20'));
        self::assertTrue($assignment->isAssignedOn(new \DateTimeImmutable('2026-10-10 23:59')));
        self::assertTrue($assignment->isAssignedOn(new \DateTimeImmutable('2026-10-20 00:01')));
        self::assertFalse($assignment->isAssignedOn(new \DateTimeImmutable('2026-10-21')));
        $assignment->setLeftAt(new \DateTime('2026-10-09'));
        self::assertGreaterThan(0, $this->validateAssignment($assignment)->count());
    }
}
