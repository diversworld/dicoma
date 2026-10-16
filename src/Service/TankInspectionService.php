<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Tank;
use App\Entity\TankConditionHistory;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

final class TankInspectionService
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    public function record(int $tankId, int $clubId, string $performed, string $next, string $reference, string $actor): void
    {
        $reference = trim($reference);
        if (mb_strlen($reference) < 10 || mb_strlen($reference) > 1000) {
            throw new \DomainException('Prüfprotokoll/Referenz muss 10 bis 1000 Zeichen enthalten.');
        }
        $performedDate = $this->parseDate($performed);
        $nextDate = $this->parseDate($next);
        $today = new \DateTimeImmutable('today');
        if ($performedDate > $today) {
            throw new \DomainException('Das Prüfdatum darf nicht in der Zukunft liegen.');
        }
        if ($nextDate <= $performedDate) {
            throw new \DomainException('Die nächste Prüfung muss nach dem Prüfdatum liegen.');
        }

        $this->em->wrapInTransaction(function () use ($tankId, $clubId, $performedDate, $nextDate, $reference, $actor): void {
            $tank = $this->em->find(Tank::class, $tankId, LockMode::PESSIMISTIC_WRITE);
            if (!$tank || $tank->getClub()?->getId() !== $clubId) {
                throw new \DomainException('Flasche nicht gefunden oder nicht für diesen Verein freigegeben.');
            }
            $existing = $tank->getLastCheckDate();
            if ($existing && $performedDate < \DateTimeImmutable::createFromInterface($existing)->setTime(0, 0)) {
                throw new \DomainException('Das Prüfdatum darf nicht vor der zuletzt erfassten Prüfung liegen.');
            }
            $tank->setLastCheckDate(\DateTime::createFromImmutable($performedDate));
            $tank->setNextCheckDate(\DateTime::createFromImmutable($nextDate));
            $this->em->persist(new TankConditionHistory(
                $tank,
                'inspection_recorded',
                $tank->getConditionStatus(),
                $tank->isLendable(),
                sprintf('[%s] Prüfung %s; nächste Prüfung %s; Protokoll: %s', $actor, $performedDate->format('Y-m-d'), $nextDate->format('Y-m-d'), $reference)
            ));
            // No status change or automatic release. Existing release guards remain authoritative.
        });
    }

    private function parseDate(string $value): \DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) {
            throw new \DomainException('Bitte ein gültiges Datum im Format JJJJ-MM-TT eingeben.');
        }
        return $date;
    }
}
