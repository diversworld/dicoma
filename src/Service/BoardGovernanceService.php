<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\BoardResolution;
use App\Entity\BoardTask;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

final class BoardGovernanceService
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    public function createTaskFromResolution(BoardResolution $resolution, User $actor): BoardTask
    {
        if ($resolution->getId() !== null && $this->em->getRepository(BoardTask::class)->count(['resolution' => $resolution]) > 0) {
            throw new \DomainException('Für diesen Beschluss wurde bereits eine Aufgabe angelegt.');
        }
        if ($resolution->getStatus() === BoardResolution::STATUS_CANCELLED || $resolution->getStatus() === BoardResolution::STATUS_COMPLETED) {
            throw new \DomainException('Aus einem abgeschlossenen oder verworfenen Beschluss kann keine Aufgabe erzeugt werden.');
        }
        $task = new BoardTask($resolution->getMeeting()->getClub(), $actor, $resolution->getTitle());
        $task->setDescription($resolution->getDecision())
            ->setPriority(BoardTask::PRIORITY_HIGH)
            ->setAssignee($resolution->getResponsible())
            ->setDueAt($resolution->getDueAt())
            ->setResolution($resolution);
        $this->em->persist($task);
        return $task;
    }
}
