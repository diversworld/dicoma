<?php

declare(strict_types=1);
namespace App\Command;

use App\Entity\BarDutyNotification;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputInterface, InputOption};
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(name: 'app:bar-duty:send', description: 'Versendet Thekendienstnachrichten nur mit --send; interner Posteingang bleibt unabhängig nutzbar.')]
final class SendBarDutyNotificationsCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $em, private readonly MailerInterface $mailer,
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir) { parent::__construct(); }
    protected function configure(): void
    {
        $this->addOption('send', null, InputOption::VALUE_NONE, 'Ausdrücklich E-Mails versenden')
            ->addOption('retry', null, InputOption::VALUE_NONE, 'Fehlgeschlagene Nachrichten erneut versuchen')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Maximal 1–500 Nachrichten', '100');
    }
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$input->getOption('send')) { $output->writeln('Kein Versand: --send erforderlich. Nachrichten sind im Portal verfügbar.'); return Command::SUCCESS; }
        $from = (string) ($_SERVER['BAR_DUTY_MAIL_FROM'] ?? $_ENV['BAR_DUTY_MAIL_FROM'] ?? '');
        $limit = filter_var($input->getOption('limit'), FILTER_VALIDATE_INT);
        if (!filter_var($from, FILTER_VALIDATE_EMAIL) || $limit === false || $limit < 1 || $limit > 500) { $output->writeln('<error>BAR_DUTY_MAIL_FROM und limit (1–500) prüfen.</error>'); return Command::INVALID; }
        $lock = fopen($this->projectDir.'/var/bar-duty-notifications.lock', 'c');
        if ($lock === false) { throw new \RuntimeException('Versandsperre nicht verfügbar.'); }
        if (!flock($lock, LOCK_EX | LOCK_NB)) { fclose($lock); $output->writeln('<error>Versand läuft bereits.</error>'); return Command::FAILURE; }
        $sent = 0; $failed = 0;
        $unresolved = $this->em->getRepository(BarDutyNotification::class)->count(['mailState' => 'sending']);
        if ($unresolved > 0) { $output->writeln('<error>'.$unresolved.' Nachrichten mit unklarem Versandstatus (sending). Transportprotokoll prüfen; kein automatischer Neuversand.</error>'); }
        try {
            $rows = $this->em->getRepository(BarDutyNotification::class)->findBy(['mailState' => $input->getOption('retry') ? 'failed' : 'pending'], ['id' => 'ASC'], $limit);
            foreach ($rows as $row) {
                $recipient = null;
                $this->em->wrapInTransaction(function () use ($row, &$recipient, &$failed): void {
                    $this->em->refresh($row, LockMode::PESSIMISTIC_WRITE);
                    if (!in_array($row->getMailState(), ['pending', 'failed'], true)) { return; }
                    $recipient = $row->getMember()->getEmail();
                    if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) { $row->mailResult('failed', 'Mitglied hat keine gültige Kontakt-E-Mail.'); ++$failed; $recipient = null; return; }
                    $row->mailResult('sending');
                });
                if ($recipient === null) { continue; }
                try {
                    $this->mailer->send((new Email())->from($from)->to($recipient)->subject($row->getSubject())
                        ->text($row->getBody()."\n\nDies ist eine Ereignisnachricht. Maßgeblich ist die aktuelle Einteilung im Thekendienstportal."));
                    $row->mailResult('sent'); ++$sent;
                } catch (TransportExceptionInterface $e) {
                    $row->mailResult('failed', $e->getMessage()); ++$failed;
                    $output->writeln('<error>Nachricht #'.$row->getId().' konnte nicht gesendet werden.</error>');
                }
                $this->em->flush();
            }
        } finally { flock($lock, LOCK_UN); fclose($lock); }
        $output->writeln($sent.' gesendet, '.$failed.' fehlgeschlagen.'); return $failed > 0 || $unresolved > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
