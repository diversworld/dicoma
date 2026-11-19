<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\BoardTask;
use App\Entity\MembershipMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:board:task-reminders', description: 'Bereitet fällige Aufgaben-Erinnerungen in der Nachrichten-Outbox vor.')]
final class BoardTaskRemindersCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $em) { parent::__construct(); }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $now = new \DateTimeImmutable();
        $tasks = $this->em->getRepository(BoardTask::class)->createQueryBuilder('task')
            ->join('task.assignee', 'assignee')->addSelect('assignee')
            ->where('task.remindAt <= :now')
            ->andWhere('task.reminderSentAt IS NULL')
            ->andWhere('task.status IN (:open)')
            ->setParameter('now', $now)
            ->setParameter('open', [BoardTask::STATUS_OPEN, BoardTask::STATUS_IN_PROGRESS])
            ->getQuery()->getResult();
        $queued = 0;
        foreach ($tasks as $task) {
            $email = $task->getAssignee()?->getEmail();
            if (!is_string($email) || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                continue;
            }
            $key = 'board-task:'.$task->getId().':'.$task->getRemindAt()?->format('YmdHi');
            if ($this->em->getRepository(MembershipMessage::class)->findOneBy(['messageKey' => $key]) !== null) {
                continue;
            }
            $this->em->persist(new MembershipMessage(
                $key,
                $email,
                'Erinnerung: '.$task->getTitle(),
                sprintf("Die Aufgabe \"%s\" ist %s fällig.\n\nStatus: %s\n\nBitte im Verwaltungsportal bearbeiten.", $task->getTitle(), $task->getDueAt()?->format('d.m.Y H:i') ?? 'bald', $task->getStatus()),
            ));
            $task->markReminderSent();
            ++$queued;
        }
        $this->em->flush();
        $output->writeln($queued.' Aufgaben-Erinnerungen vorbereitet.');
        return Command::SUCCESS;
    }
}
