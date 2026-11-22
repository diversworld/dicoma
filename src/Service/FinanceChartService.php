<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Club;
use App\Entity\FinanceAccount;
use App\Finance\Skr42Chart;
use Doctrine\ORM\EntityManagerInterface;

final class FinanceChartService
{
    public function __construct(private readonly EntityManagerInterface $em, private readonly AuditLogger $audit) {}

    /** @return array{created:int,updated:int,total:int} */
    public function installSkr42(Club $club, string $actor): array
    {
        $summary = ['created' => 0, 'updated' => 0, 'total' => 0];
        foreach (Skr42Chart::accounts() as $row) {
            $account = $this->em->getRepository(FinanceAccount::class)->findOneBy(['club' => $club, 'code' => $row['code']]);
            if ($account === null) {
                $account = new FinanceAccount(
                    $club,
                    $row['code'],
                    $row['name'],
                    $row['type'],
                    Skr42Chart::NAME,
                    $row['sphere'],
                    $row['usage'],
                    Skr42Chart::SOURCE,
                );
                $this->em->persist($account);
                ++$summary['created'];
            } elseif ($account->getChart() !== Skr42Chart::NAME || $account->getSphere() !== $row['sphere']) {
                $account->applyChartMetadata(Skr42Chart::NAME, $row['sphere'], $row['usage'], Skr42Chart::SOURCE);
                ++$summary['updated'];
            }
            ++$summary['total'];
        }
        if ($summary['created'] > 0 || $summary['updated'] > 0) {
            $this->audit->record($club, $actor, 'finance.chart.imported', $club, [
                'chart' => Skr42Chart::NAME,
                'created' => $summary['created'],
                'updated' => $summary['updated'],
            ]);
        }
        return $summary;
    }
}
