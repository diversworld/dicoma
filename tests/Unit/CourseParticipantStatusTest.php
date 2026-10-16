<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Enum\CourseParticipantStatus;
use PHPUnit\Framework\TestCase;

final class CourseParticipantStatusTest extends TestCase
{
    public function testFinalStatusesAreDistinctFromActiveStatuses(): void
    {
        $final = [CourseParticipantStatus::PASSED, CourseParticipantStatus::FAILED, CourseParticipantStatus::CANCELLED];
        self::assertNotContains(CourseParticipantStatus::REGISTERED, $final);
        self::assertNotContains(CourseParticipantStatus::ACTIVE, $final);
        self::assertCount(3, $final);
    }
}
