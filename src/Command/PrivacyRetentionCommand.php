<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\AuditEvent;
use App\Entity\Club;
use App\Entity\Member;
use App\Entity\MemberDocument;
use App\Entity\MembershipMessage;
use App\Entity\PrivacyPolicy;
use App\Service\PrivateMemberDocumentStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:privacy:retention', description: 'Prüft oder bereinigt DSGVO-Aufbewahrungsfristen in begrenzten Batches.')]
final class PrivacyRetentionCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PrivateMemberDocumentStorage $documents,
    ) { parent::__construct(); }

    protected function configure(): void
    {
        $this->addOption('apply', null, InputOption::VALUE_NONE, 'Berechtigungen tatsächlich ausführen')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Maximale Datensätze je Kategorie', '250');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $limit = filter_var($input->getOption('limit'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 5000]]);
        if ($limit === false) { return Command::INVALID; }
        $apply = (bool) $input->getOption('apply');
        $counts = ['audit' => 0, 'messages' => 0, 'members' => 0, 'documents' => 0];
        foreach ($this->em->getRepository(Club::class)->findAll() as $club) {
            $policy = $this->em->getRepository(PrivacyPolicy::class)->findOneBy(['club' => $club]) ?? new PrivacyPolicy($club);
            if (!$policy->isPurgeEnabled()) { continue; }
            $today = new \DateTimeImmutable('today');
            $counts['audit'] += $this->purgeAudit($club, $today, $apply, $limit);
            $counts['messages'] += $this->purgeMessages($club, $today->modify('-'.$policy->getMessageRetentionDays().' days'), $apply, $limit);
            $memberResult = $this->anonymizeMembers(
                $club,
                $today->modify('-'.$policy->getMemberRetentionYears().' years'),
                $today->modify('-'.$policy->getFinancialRetentionYears().' years'),
                $apply,
                $limit,
            );
            $counts['members'] += $memberResult['members'];
            $counts['documents'] += $memberResult['documents'];
        }
        if ($apply) { $this->em->flush(); }
        $output->writeln(($apply ? 'Bereinigt: ' : 'Dry-Run: ').json_encode($counts, JSON_UNESCAPED_SLASHES));
        if (!$apply) { $output->writeln('Keine Daten wurden verändert. Mit --apply explizit ausführen.'); }
        return Command::SUCCESS;
    }

    private function purgeAudit(Club $club, \DateTimeImmutable $today, bool $apply, int $limit): int
    {
        $events = $this->em->getRepository(AuditEvent::class)->findBy(['club' => $club], ['expiresAt' => 'ASC'], $limit);
        $expired = array_values(array_filter($events, static fn (AuditEvent $event): bool => $event->getExpiresAt() < $today));
        if ($apply) { foreach ($expired as $event) { $this->em->remove($event); } }
        return count($expired);
    }

    private function purgeMessages(Club $club, \DateTimeImmutable $cutoff, bool $apply, int $limit): int
    {
        $recipients = array_values(array_unique(array_filter(array_map(
            static fn (Member $member): ?string => $member->getPerson()->getEmail(),
            $this->em->getRepository(Member::class)->findBy(['club' => $club]),
        ))));
        if ($recipients === []) { return 0; }
        $messages = $this->em->getRepository(MembershipMessage::class)->createQueryBuilder('message')
            ->where('message.recipient IN (:recipients)')
            ->andWhere('message.sentAt IS NOT NULL AND message.sentAt <= :cutoff')
            ->setParameter('recipients', $recipients)->setParameter('cutoff', $cutoff)
            ->setMaxResults($limit)->getQuery()->getResult();
        if ($apply) { foreach ($messages as $message) { $this->em->remove($message); } }
        return count($messages);
    }

    /** @return array{members:int,documents:int} */
    private function anonymizeMembers(Club $club, \DateTimeImmutable $memberCutoff, \DateTimeImmutable $financialCutoff, bool $apply, int $limit): array
    {
        $members = $this->em->getRepository(Member::class)->createQueryBuilder('m')
            ->where('m.club = :club')->andWhere('m.archivedAt IS NOT NULL AND m.archivedAt <= :cutoff')
            ->setParameter('club', $club)->setParameter('cutoff', $memberCutoff)->setMaxResults($limit)
            ->getQuery()->getResult();
        $count = 0; $documents = 0;
        foreach ($members as $member) {
            if ($member->getMembershipStatus()->value !== 'former') { continue; }
            $person = $member->getPerson();
            $otherMemberships = $this->em->getRepository(Member::class)->count(['person' => $person]);
            if ($otherMemberships !== 1) { continue; }
            $financial = $this->em->getConnection()->fetchOne(
                'SELECT COUNT(*) FROM membership_charge charge LEFT JOIN membership_payment payment ON payment.charge_id = charge.id WHERE charge.member_id = ? AND (charge.created_at > ? OR payment.received_at > ?)',
                [$member->getId(), $financialCutoff->format('Y-m-d H:i:s'), $financialCutoff->format('Y-m-d H:i:s')],
            );
            if ((int) $financial > 0) { continue; }
            ++$count;
            $files = $this->em->getRepository(MemberDocument::class)->findBy(['member' => $member]);
            $documents += count($files);
            if (!$apply) { continue; }
            foreach ($this->em->getRepository(\App\Entity\SepaMandate::class)->findBy(['member' => $member]) as $mandate) { $this->em->remove($mandate); }
            foreach ($files as $file) {
                $path = $this->documents->path($file->getStorageName());
                if (is_file($path) && !unlink($path)) { throw new \RuntimeException('Mitgliedsdokument konnte nicht gelöscht werden.'); }
                $this->em->remove($file);
            }
            $profileImage = $person->getProfileImageStorageName();
            if ($profileImage !== null) {
                $imagePath = $this->documents->path($profileImage);
                if (is_file($imagePath) && !unlink($imagePath)) { throw new \RuntimeException('Profilbild konnte nicht gelöscht werden.'); }
                $person->setProfileImageStorageName(null);
            }
            $person->setFirstname('Anonymisiert')->setLastname('Mitglied '.substr(hash('sha256', (string) $member->getId()), 0, 8))
                ->setBirthday(null)->setGender(null)->setEmail(null)->setMobile(null)->setPhone(null)
                ->setStreet(null)->setPostalCode(null)->setCity(null);
            $member->setNotes(null);
            if ($member->getUser() !== null) {
                $member->getUser()->setUser('anonymized-'.$member->getId())
                    ->setEmail('deleted-'.$member->getId().'@invalid.example');
            }
            foreach ($this->em->getRepository(\App\Entity\MembershipEvent::class)->findBy(['member' => $member]) as $event) {
                $this->em->remove($event);
            }
        }
        return ['members' => $count, 'documents' => $documents];
    }
}
