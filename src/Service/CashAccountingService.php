<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\BarCashEntry;
use App\Entity\BarCashSession;
use App\Entity\Club;
use App\Entity\EquipmentCashClosing;
use App\Entity\FinanceAccount;
use App\Entity\FinanceTransaction;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

final class CashAccountingService
{
    public const COMPRESSOR_ACCOUNT = '19110';
    public const BAR_ACCOUNT = '19120';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly FinanceLedgerService $ledger,
    ) {}

    /** @return list<array{id:int,type:string,label:string,date:\DateTimeImmutable,incomeCents:int,expenseCents:int,posted:bool}> */
    public function pending(Club $club): array
    {
        $rows = [];
        foreach ($this->em->getRepository(EquipmentCashClosing::class)->findBy(['club' => $club], ['closingDate' => 'DESC']) as $closing) {
            $rows[] = [
                'id' => $closing->getId(),
                'type' => 'equipment',
                'label' => 'Geräte-/Kompressorkasse '.$closing->getClosingDate()->format('d.m.Y'),
                'date' => $closing->getClosingDate(),
                'incomeCents' => $closing->getCashInCents(),
                'expenseCents' => $closing->getCashOutCents(),
                'posted' => $this->isPosted($club, 'cash:equipment:'.$closing->getId()),
            ];
        }
        foreach ($this->em->getRepository(BarCashSession::class)->createQueryBuilder('session')
            ->join('session.shift', 'shift')->addSelect('shift')
            ->where('shift.club = :club AND session.closedAt IS NOT NULL')
            ->setParameter('club', $club)->orderBy('session.closedAt', 'DESC')->setMaxResults(100)
            ->getQuery()->getResult() as $session) {
            $totals = $this->barTotals($session);
            $rows[] = [
                'id' => $session->getId(),
                'type' => 'bar',
                'label' => 'Thekenkasse '.$session->getShift()->getTitle().' '.$session->getClosedAt()?->format('d.m.Y'),
                'date' => $session->getClosedAt()?->setTime(0, 0) ?? new \DateTimeImmutable('today'),
                'incomeCents' => $totals['income'],
                'expenseCents' => $totals['expense'],
                'posted' => $this->isPosted($club, 'cash:bar:'.$session->getId()),
            ];
        }
        usort($rows, static fn (array $a, array $b): int => $b['date'] <=> $a['date']);
        return $rows;
    }

    /** @return list<FinanceTransaction> */
    public function book(Club $club, string $type, int $id, FinanceAccount $cash, FinanceAccount $income, FinanceAccount $expense, User $actor): array
    {
        $this->assertAccount($club, $cash, FinanceAccount::TYPE_ASSET);
        $this->assertAccount($club, $income, FinanceAccount::TYPE_INCOME);
        $this->assertAccount($club, $expense, FinanceAccount::TYPE_EXPENSE);
        $sourceKey = 'cash:'.$type.':'.$id;
        if ($this->isPosted($club, $sourceKey)) {
            throw new \DomainException('Dieser Kassenabschluss wurde bereits übernommen.');
        }
        if ($type === 'equipment') {
            $closing = $this->em->find(EquipmentCashClosing::class, $id);
            if (!$closing instanceof EquipmentCashClosing || $closing->getClub()?->getId() !== $club->getId()) {
                throw new \DomainException('Gerätekassenabschluss nicht gefunden.');
            }
            $date = $closing->getClosingDate();
            $incomeCents = $closing->getCashInCents();
            $expenseCents = $closing->getCashOutCents();
            $reference = 'GERÄTE-'.$id;
            $description = 'Geräte-/Kompressorkasse '.$date->format('d.m.Y');
        } elseif ($type === 'bar') {
            $session = $this->em->find(BarCashSession::class, $id);
            if (!$session instanceof BarCashSession || $session->getShift()->getClub()->getId() !== $club->getId() || $session->getClosedAt() === null) {
                throw new \DomainException('Abgeschlossene Thekenkasse nicht gefunden.');
            }
            $date = $session->getClosedAt()->setTime(0, 0);
            $totals = $this->barTotals($session);
            $incomeCents = $totals['income'];
            $expenseCents = $totals['expense'];
            $reference = 'THEKE-'.$id;
            $description = 'Thekenkasse '.$session->getShift()->getTitle();
        } else {
            throw new \DomainException('Unbekannte Kassenart.');
        }
        if ($cash->getCode() !== ($type === 'bar' ? self::BAR_ACCOUNT : self::COMPRESSOR_ACCOUNT)) {
            throw new \DomainException('Bitte das passende Nebenkassenkonto der Kassenart auswählen.');
        }
        $transactions = [];
        $this->em->wrapInTransaction(function () use (&$transactions, $club, $cash, $income, $expense, $incomeCents, $expenseCents, $date, $description, $sourceKey, $reference, $actor): void {
            if ($incomeCents > 0) {
                $transactions[] = $this->ledger->post($club, $cash, $income, $incomeCents, $date, $description.' – Einnahmen', $sourceKey.':income', $actor, $reference);
            }
            if ($expenseCents > 0) {
                $transactions[] = $this->ledger->post($club, $expense, $cash, $expenseCents, $date, $description.' – Ausgaben', $sourceKey.':expense', $actor, $reference);
            }
            if ($transactions === []) {
                throw new \DomainException('Der Abschluss enthält keine buchbaren Bewegungen.');
            }
        });
        return $transactions;
    }

    public function transfer(Club $club, FinanceAccount $fromCash, FinanceAccount $targetCash, int $amountCents, \DateTimeImmutable $date, User $actor, string $reference): void
    {
        $this->assertAccount($club, $fromCash, FinanceAccount::TYPE_ASSET);
        $this->assertAccount($club, $targetCash, FinanceAccount::TYPE_ASSET);
        if (!in_array($fromCash->getCode(), [self::COMPRESSOR_ACCOUNT, self::BAR_ACCOUNT, '19100'], true)
            || $amountCents <= 0 || trim($reference) === '') {
            throw new \DomainException('Gültige Quellkasse, Zielkasse, Betrag und Übergabereferenz erforderlich.');
        }
        $this->ledger->post(
            $club,
            $targetCash,
            $fromCash,
            $amountCents,
            $date,
            'Bargeldübernahme aus Nebenkasse',
            'cash-transfer:'.bin2hex(random_bytes(12)),
            $actor,
            mb_substr(trim($reference), 0, 180),
        );
    }

    private function isPosted(Club $club, string $sourceKey): bool
    {
        return $this->em->getRepository(FinanceTransaction::class)->count(['club' => $club, 'sourceKey' => $sourceKey.':income']) > 0
            || $this->em->getRepository(FinanceTransaction::class)->count(['club' => $club, 'sourceKey' => $sourceKey.':expense']) > 0;
    }

    /** @return array{income:int,expense:int} */
    private function barTotals(BarCashSession $session): array
    {
        $income = 0;
        $expense = 0;
        foreach ($this->em->getRepository(BarCashEntry::class)->findBy(['session' => $session]) as $entry) {
            if ($entry->getAmountCents() > 0) { $income += $entry->getAmountCents(); }
            else { $expense -= $entry->getAmountCents(); }
        }
        return ['income' => $income, 'expense' => $expense];
    }

    private function assertAccount(Club $club, FinanceAccount $account, string $type): void
    {
        if ($account->getClub()->getId() !== $club->getId() || !$account->isActive() || $account->getType() !== $type) {
            throw new \DomainException('Buchungskonto ist ungültig oder gehört nicht zum Verein.');
        }
    }
}
