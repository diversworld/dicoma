<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\CourseAuditEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

final class CourseAuditLogger
{
    public function __construct(private readonly EntityManagerInterface $em, private readonly Security $security) {}

    /** Call inside the SAME transaction as the business mutation; do not flush here. */
    public function record(int $courseId, ?int $participantId, string $action, ?string $before, string $after, ?string $reason = null): void
    {
        $user = $this->security->getUser();
        $actorId = $user !== null && method_exists($user, 'getId') ? $user->getId() : null;
        $this->em->persist(new CourseAuditEvent($courseId, $participantId, is_int($actorId) ? $actorId : null, $action, $before, $after, $reason));
    }
}
