<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\BoardMeeting;
use App\Entity\BoardResolution;
use App\Entity\BoardTask;
use App\Entity\Club;
use App\Entity\FinanceAnnualClose;
use App\Entity\Member;
use App\Entity\MembershipPayment;
use Doctrine\ORM\EntityManagerInterface;

final class ClubAnnualExport
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    /** @return array{path:string,filename:string} */
    public function create(Club $club, int $year): array
    {
        if ($year < 2000 || $year >= (int) date('Y')) {
            throw new \DomainException('Bitte ein abgeschlossenes Jahr zwischen 2000 und dem aktuellen Vorjahr wählen.');
        }
        $from = new \DateTimeImmutable($year.'-01-01');
        $until = new \DateTimeImmutable($year.'-12-31 23:59:59');
        $meetings = $this->em->getRepository(BoardMeeting::class)->createQueryBuilder('meeting')
            ->where('meeting.club = :club AND meeting.startsAt BETWEEN :from AND :until')
            ->setParameter('club', $club)->setParameter('from', $from)->setParameter('until', $until)
            ->orderBy('meeting.startsAt', 'ASC')->getQuery()->getResult();
        $tasks = $this->em->getRepository(BoardTask::class)->createQueryBuilder('task')
            ->where('task.club = :club AND task.createdAt BETWEEN :from AND :until')
            ->setParameter('club', $club)->setParameter('from', $from)->setParameter('until', $until)
            ->orderBy('task.createdAt', 'ASC')->getQuery()->getResult();
        $resolutions = $this->em->getRepository(BoardResolution::class)->createQueryBuilder('resolution')
            ->join('resolution.meeting', 'meeting')
            ->where('meeting.club = :club AND resolution.createdAt BETWEEN :from AND :until')
            ->setParameter('club', $club)->setParameter('from', $from)->setParameter('until', $until)
            ->orderBy('resolution.createdAt', 'ASC')->getQuery()->getResult();
        $members = $this->em->getRepository(Member::class)->findBy(['club' => $club], ['memberNumber' => 'ASC', 'id' => 'ASC']);
        $close = $this->em->getRepository(FinanceAnnualClose::class)->findOneBy(['club' => $club, 'year' => $year]);
        $payments = $this->em->getRepository(MembershipPayment::class)->createQueryBuilder('payment')
            ->join('payment.charge', 'charge')->join('charge.member', 'm')
            ->where('m.club = :club AND payment.receivedAt BETWEEN :from AND :until')
            ->setParameter('club', $club)->setParameter('from', $from)->setParameter('until', $until)
            ->orderBy('payment.receivedAt', 'ASC')->getQuery()->getResult();

        $memberRows = [['Mitgliedsnummer', 'Vorname', 'Nachname', 'Status', 'Eintritt', 'Austritt']];
        foreach ($members as $member) {
            $memberRows[] = [
                $member->getMemberNumber() ?? '',
                $member->getFirstname() ?? '',
                $member->getLastname() ?? '',
                $member->getMembershipStatus()->value,
                $member->getJoinedAt()?->format('Y-m-d') ?? '',
                $member->getLeftAt()?->format('Y-m-d') ?? '',
            ];
        }
        $financeRows = [['Datum', 'Mitglied', 'Rechnung', 'Beschreibung', 'Betrag Cent', 'Referenz']];
        foreach ($payments as $payment) {
            $charge = $payment->getCharge();
            $financeRows[] = [
                $payment->getReceivedAt()->format('Y-m-d'),
                $charge->getMember()->getFullName(),
                $charge->getInvoiceNumber() ?? '',
                $charge->getDescription(),
                (string) $payment->getAmountCents(),
                $payment->getReference(),
            ];
        }
        $report = [
            'club' => $club->getName(),
            'year' => $year,
            'generatedAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
            'members' => [
                'total' => count($members),
                'active' => count(array_filter($members, static fn (Member $member): bool => $member->getMembershipStatus()->value === 'active')),
                'passive' => count(array_filter($members, static fn (Member $member): bool => $member->getMembershipStatus()->value === 'passive')),
            ],
            'governance' => ['meetings' => count($meetings), 'resolutions' => count($resolutions), 'tasks' => count($tasks)],
            'finance' => [
                'closed' => $close !== null,
                'incomeCents' => $close?->getIncomeCents(),
                'expenseCents' => $close?->getExpenseCents(),
                'resultCents' => $close?->getResultCents(),
                'paymentsCents' => array_sum(array_map(static fn (MembershipPayment $payment): int => $payment->getAmountCents(), $payments)),
            ],
            'meetings' => array_map(static fn (BoardMeeting $meeting): array => [
                'date' => $meeting->getStartsAt()->format('Y-m-d H:i'),
                'title' => $meeting->getTitle(),
                'status' => $meeting->getStatus(),
                'minutesPresent' => trim((string) $meeting->getMinutes()) !== '',
            ], $meetings),
        ];

        $path = tempnam(sys_get_temp_dir(), 'dicoma-annual-');
        if ($path === false || !class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('ZIP-Export ist auf diesem Server nicht verfügbar.');
        }
        $zip = new \ZipArchive();
        if ($zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Exportpaket konnte nicht geöffnet werden.');
        }
        $zip->addFromString('jahresbericht.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        $zip->addFromString('mitglieder.csv', $this->csv($memberRows));
        $zip->addFromString('zahlungen.csv', $this->csv($financeRows));
        $zip->close();
        return ['path' => $path, 'filename' => sprintf('dicoma-jahresbericht-%d.zip', $year)];
    }

    /** @param list<list<string>> $rows */
    private function csv(array $rows): string
    {
        $stream = fopen('php://temp', 'w+');
        if ($stream === false) { throw new \RuntimeException('CSV-Export konnte nicht erstellt werden.'); }
        fwrite($stream, "\xEF\xBB\xBF");
        foreach ($rows as $row) { fputcsv($stream, $row, ';', '"', '\\'); }
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);
        if ($csv === false) { throw new \RuntimeException('CSV-Export konnte nicht gelesen werden.'); }
        return $csv;
    }
}
