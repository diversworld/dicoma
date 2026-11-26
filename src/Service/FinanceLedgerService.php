<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Club;
use App\Entity\FinanceAccount;
use App\Entity\FinanceAnnualClose;
use App\Entity\FinanceTransaction;
use App\Entity\User;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

final class FinanceLedgerService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AuditLogger $audit,
        private readonly ProductionReadiness $readiness,
    ) {}

    public function post(Club $club, FinanceAccount $debit, FinanceAccount $credit, int $amountCents, \DateTimeImmutable $date, string $description, string $sourceKey, User $actor, ?string $reference = null): FinanceTransaction
    {
        if (!$debit->isActive() || !$credit->isActive()) { throw new \DomainException('Nur aktive Konten dürfen bebucht werden.'); }
        if ($this->em->getRepository(FinanceAnnualClose::class)->findOneBy(['club' => $club, 'year' => (int) $date->format('Y')]) !== null) {
            throw new \DomainException('Das Geschäftsjahr ist abgeschlossen. Korrekturen benötigen eine Stornobuchung im offenen Folgejahr.');
        }
        $transaction = new FinanceTransaction($club, $debit, $credit, $amountCents, $date, $description, $sourceKey, $actor->getUserIdentifier(), $reference);
        $this->em->persist($transaction);
        $this->audit->record($club, $actor->getUserIdentifier(), 'finance.transaction.posted', $transaction, [
            'debit' => $debit->getCode(),
            'credit' => $credit->getCode(),
            'amountCents' => $amountCents,
            'effectiveAt' => $date->format('Y-m-d'),
        ]);
        return $transaction;
    }

    public function reverse(FinanceTransaction $transaction, User $actor, string $reason): FinanceTransaction
    {
        if ($transaction->isReversed()) { throw new \DomainException('Buchung ist bereits storniert.'); }
        $today = new \DateTimeImmutable('today');
        $reversal = $this->post(
            $transaction->getClub(),
            $transaction->getCreditAccount(),
            $transaction->getDebitAccount(),
            $transaction->getAmountCents(),
            $today,
            'Storno: '.$reason,
            'reversal:'.$transaction->getId().':'.bin2hex(random_bytes(4)),
            $actor,
            'Storno-'.$transaction->getId(),
        );
        $transaction->attachReversal($reversal);
        $this->audit->record($transaction->getClub(), $actor->getUserIdentifier(), 'finance.transaction.reversed', $transaction, ['reversal' => $reason]);
        return $reversal;
    }

    public function closeYear(Club $club, int $year, User $actor): FinanceAnnualClose
    {
        $this->readiness->requireApproved($club, 'chart');
        if ($this->em->getRepository(FinanceAnnualClose::class)->findOneBy(['club' => $club, 'year' => $year]) !== null) {
            throw new \DomainException('Dieses Geschäftsjahr ist bereits abgeschlossen.');
        }
        if ($year >= (int) date('Y')) { throw new \DomainException('Nur abgeschlossene Kalenderjahre dürfen fixiert werden.'); }
        $accounts = $this->em->getRepository(FinanceAccount::class)->findBy(['club' => $club], ['code' => 'ASC']);
        $balances = [];
        $income = 0;
        $expense = 0;
        foreach ($accounts as $account) {
            $balance = $this->balanceUntil($account, new \DateTimeImmutable($year.'-12-31'));
            $balances[$account->getCode()] = $balance;
            if ($account->getType() === FinanceAccount::TYPE_INCOME) { $income += -$balance; }
            if ($account->getType() === FinanceAccount::TYPE_EXPENSE) { $expense += $balance; }
        }
        ksort($balances);
        $hash = hash('sha256', json_encode(['year' => $year, 'balances' => $balances, 'income' => $income, 'expense' => $expense], JSON_THROW_ON_ERROR));
        $close = new FinanceAnnualClose($club, $year, $income, $expense, $balances, $hash, $actor);
        $this->em->persist($close);
        $this->audit->record($club, $actor->getUserIdentifier(), 'finance.year.closed', $close, ['year' => $year, 'sha256' => $hash]);
        return $close;
    }

    public function balanceUntil(FinanceAccount $account, ?\DateTimeImmutable $date = null): int
    {
        $qb = $this->em->getRepository(FinanceTransaction::class)->createQueryBuilder('transaction')
            ->where('transaction.debitAccount = :account OR transaction.creditAccount = :account')
            ->setParameter('account', $account);
        if ($date !== null) {
            $qb->andWhere('transaction.effectiveAt <= :date')->setParameter('date', $date);
        }
        $balance = 0;
        foreach ($qb->getQuery()->getResult() as $transaction) {
            $balance += $transaction->getDebitAccount()->getId() === $account->getId() ? $transaction->getAmountCents() : -$transaction->getAmountCents();
        }
        return $balance;
    }
}
