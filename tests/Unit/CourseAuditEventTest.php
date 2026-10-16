<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\CourseAuditEvent;
use PHPUnit\Framework\TestCase;

final class CourseAuditEventTest extends TestCase
{
    public function testAuditEventCapturesStatusAndReason(): void
    {
        $event = new CourseAuditEvent(7, 11, 3, 'participant_failed', 'active', 'failed', 'Prüfung nicht bestanden');
        self::assertSame(7, $event->getCourseId());
        self::assertSame(11, $event->getParticipantId());
        self::assertSame(3, $event->getActorUserId());
        self::assertSame('active', $event->getOldStatus());
        self::assertSame('failed', $event->getNewStatus());
        self::assertSame('Prüfung nicht bestanden', $event->getReason());
        self::assertInstanceOf(\DateTimeImmutable::class, $event->getOccurredAt());
    }

    public function testRejectsInvalidCourseId(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new CourseAuditEvent(0, null, null, 'course_completed', 'running', 'completed');
    }
}
