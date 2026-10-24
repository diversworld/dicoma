<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\MembershipMessage;
use App\Service\MembershipAutomation;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

#[AsCommand(name: 'app:membership:daily', description: 'Wirksame Austritte und Mitgliedererinnerungen verarbeiten; Mailversand nur mit --send.')]
final class MembershipDailyCommand extends Command
{
    public function __construct(
        private readonly MembershipAutomation $automation,
        private readonly EntityManagerInterface $em,
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
    ) { parent::__construct(); }

    protected function configure(): void
    {
        $this->addOption('send', null, InputOption::VALUE_NONE, 'Wartende Benachrichtigungen versenden.')
            ->addOption('retry-failed', null, InputOption::VALUE_NONE, 'Mit --send zusätzlich fehlgeschlagene Nachrichten erneut versuchen.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($input->getOption('retry-failed') && !$input->getOption('send')) {
            $output->writeln('<error>--retry-failed erfordert --send.</error>');
            return Command::INVALID;
        }
        $lock = fopen($this->projectDir . '/var/membership-daily.lock', 'c');
        if ($lock === false) { throw new \RuntimeException('Mitglieder-Verarbeitungssperre nicht verfügbar.'); }
        if (!flock($lock, LOCK_EX | LOCK_NB)) {
            fclose($lock);
            $output->writeln('<error>Mitgliederverarbeitung läuft bereits.</error>');
            return Command::FAILURE;
        }
        try {
            $count = $this->automation->run(new \DateTimeImmutable('today'));
            $output->writeln('Wirksame Austritte: ' . $count);
            if (!$input->getOption('send')) {
                $output->writeln('Benachrichtigungen vorbereitet. Kein Mailversand ohne --send.');
                return Command::SUCCESS;
            }
            $from = $_SERVER['MEMBERSHIP_MAIL_FROM'] ?? $_ENV['MEMBERSHIP_MAIL_FROM'] ?? getenv('MEMBERSHIP_MAIL_FROM');
            if (!is_string($from) || !filter_var($from, FILTER_VALIDATE_EMAIL)) {
                $output->writeln('<error>MEMBERSHIP_MAIL_FROM fehlt oder ist ungültig; kein Versand.</error>');
                return Command::FAILURE;
            }
            $states = $input->getOption('retry-failed') ? ['pending', 'failed'] : ['pending'];
            $messages = $this->em->getRepository(MembershipMessage::class)->findBy(['state' => $states], ['id' => 'ASC'], 200);
            $failed = 0;
            foreach ($messages as $message) {
                try {
                    $this->mailer->send((new Email())->from($from)->to($message->getRecipient())->subject($message->getSubject())->text($message->getBody()));
                    $message->sent();
                } catch (TransportExceptionInterface $exception) {
                    $message->fail('Transportfehler; Details im geschützten Anwendungsprotokoll.');
                    $this->logger->error('Mitgliederbenachrichtigung konnte nicht versendet werden.', ['message_id' => $message->getId(), 'exception' => $exception]);
                    ++$failed;
                }
                $this->em->flush();
            }
            $output->writeln(sprintf('Verarbeitet: %d, fehlgeschlagen: %d.', count($messages), $failed));
            return $failed === 0 ? Command::SUCCESS : Command::FAILURE;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
