<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Club;
use App\Entity\CompressorFillBatch;
use App\Entity\CompressorFillCylinder;
use App\Entity\CompressorFillLog;
use App\Entity\EquipmentFillCharge;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

final class EquipmentFillingService
{
    public function __construct(private readonly EntityManagerInterface $em, private readonly TankFillingEligibility $eligibility) {}

    /** @param list<CompressorFillCylinder> $cylinders */
    public function save(Club $club, CompressorFillLog|CompressorFillBatch $source, string $fee, array $cylinders = []): void
    {
        $cents = TuvPriceCalculator::cents($fee);
        if ($cents === null || $cents > 999999999) { throw new \DomainException('Füllgebühr ausdrücklich angeben; 0 bedeutet kostenlos.'); }
        $this->em->wrapInTransaction(function () use ($club, $source, $cents, $cylinders): void {
            if ($source instanceof CompressorFillLog) {
                if ($source->getClub() !== $club) { throw new \DomainException('Füllung gehört nicht zum Verein.'); }
                $tanks = $source->getTank() ? [$source->getTank()] : [];
            } else {
                if (count($cylinders) < 1 || count($cylinders) > 4) { throw new \DomainException('Eine Gruppenfüllung benötigt ein bis vier Flaschen.'); }
                $session = $source->getSession();
                $this->em->refresh($session, LockMode::PESSIMISTIC_WRITE);
                if ($session->getClub()->getId() !== $club->getId() || $session->getClosedAt() !== null) { throw new \DomainException('Füllabend ist geschlossen oder gehört zu einem anderen Verein.'); }
                $tanks = [];
                foreach ($cylinders as $cylinder) {
                    if ($cylinder->getBatch() !== $source) { throw new \DomainException('Flasche gehört nicht zur Füllung.'); }
                    if ($cylinder->getTank()) { $tanks[] = $cylinder->getTank(); }
                }
            }
            usort($tanks, static fn ($a, $b): int => $a->getId() <=> $b->getId());
            $seen = [];
            foreach ($tanks as $tank) {
                if (isset($seen[$tank->getId()])) { throw new \DomainException('Dieselbe Flasche ist mehrfach ausgewählt.'); }
                $seen[$tank->getId()] = true;
                $this->em->refresh($tank, LockMode::PESSIMISTIC_WRITE);
                $this->eligibility->assertFillable($tank, $club->getId());
            }
            $this->em->persist($source);
            foreach ($cylinders as $cylinder) { $this->em->persist($cylinder); }
            $this->em->persist(new EquipmentFillCharge($club, $source, $cents));
        });
    }
}
