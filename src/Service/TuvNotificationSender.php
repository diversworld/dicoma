<?php
declare(strict_types=1);
namespace App\Service;

use App\Entity\TuvNotification;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/** All sending entry points use the same filesystem lock and persisted state. */
final class TuvNotificationSender
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MailerInterface $mailer,
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
    ) {}

    public function isImmediate(): bool { return strtolower(trim((string) ($_SERVER['TUV_SEND_MODE'] ?? $_ENV['TUV_SEND_MODE'] ?? 'manual'))) === 'immediate'; }

    /** @return string sent|failed|skipped|locked */
    public function sendOne(int $id): string
    {
        $lock = fopen($this->projectDir.'/var/tuv-notifications.lock', 'c');
        if ($lock === false) { throw new \RuntimeException('Versandsperre nicht zugänglich.'); }
        if (!flock($lock, LOCK_EX | LOCK_NB)) { fclose($lock); return 'locked'; }
        try {
            $this->em->clear(TuvNotification::class);
            $row = $this->em->find(TuvNotification::class, $id);
            if (!$row || $row->getState() !== 'pending') { return 'skipped'; }
            $from = (string) ($_SERVER['TUV_MAIL_FROM'] ?? $_ENV['TUV_MAIL_FROM'] ?? '');
            if (!filter_var($from, FILTER_VALIDATE_EMAIL)) {
                $row->markAttempt();
                $row->markFailed('TUV_MAIL_FROM fehlt oder ist ungültig.');
                $this->em->flush();
                return 'failed';
            }
            $row->markAttempt();
            $this->em->flush();
            try {
                $this->mailer->send((new Email())->from($from)->to($row->getRecipient())->subject($row->getSubject())->text($row->getBody()));
                $row->markSent();
                $this->em->flush();
                return 'sent';
            } catch (\Throwable $e) {
                $row->markFailed($e->getMessage());
                $this->em->flush();
                return 'failed';
            }
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
