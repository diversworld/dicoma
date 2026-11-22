<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\FinanceAccount;
use App\Entity\FinanceAnnualClose;
use App\Entity\FinanceTransaction;
use App\Entity\User;
use App\Enum\PermissionAction;
use App\Enum\PermissionModule;
use App\Security\ModuleAccess;
use App\Service\AdminClubContext;
use App\Service\FinanceLedgerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/accounting')]
#[IsGranted('ROLE_USER')]
final class FinanceLedgerController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AdminClubContext $clubs,
        private readonly ModuleAccess $access,
        private readonly FinanceLedgerService $ledger,
    ) {}

    #[Route('', name: 'admin_accounting', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $club = $this->clubs->getClub() ?? throw $this->createAccessDeniedException();
        $user = $this->getUser();
        if (!$user instanceof User || !$this->access->can($user, $club, PermissionModule::ACCOUNTING, PermissionAction::READ)) {
            throw $this->createAccessDeniedException();
        }
        if ($request->isMethod('POST')) {
            if (!$this->access->can($user, $club, PermissionModule::ACCOUNTING, PermissionAction::CREATE)) { throw $this->createAccessDeniedException(); }
            $this->csrf($request, 'accounting_post');
            $operation = $request->request->getString('operation');
            if ($operation === 'account') {
                $account = new FinanceAccount($club, $request->request->getString('code'), $request->request->getString('name'), $request->request->getString('type'));
                $this->em->persist($account);
                $this->em->flush();
                $this->addFlash('success', 'Konto angelegt.');
            } elseif ($operation === 'transaction') {
                $debit = $this->em->find(FinanceAccount::class, $request->request->getInt('debit'));
                $credit = $this->em->find(FinanceAccount::class, $request->request->getInt('credit'));
                if (!$debit instanceof FinanceAccount || !$credit instanceof FinanceAccount) { throw $this->createNotFoundException(); }
                $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $request->request->getString('date'));
                if ($date === false) { throw $this->createNotFoundException('Ungültiges Datum.'); }
                $this->ledger->post($club, $debit, $credit, $this->amountCents($request->request->getString('amount')), $date, $request->request->getString('description'), 'manual:'.bin2hex(random_bytes(12)), $user, $request->request->getString('reference') ?: null);
                $this->em->flush();
                $this->addFlash('success', 'Buchung gespeichert.');
            } elseif ($operation === 'close') {
                $this->ledger->closeYear($club, $request->request->getInt('year'), $user);
                $this->em->flush();
                $this->addFlash('success', 'Jahresabschluss fixiert.');
            }
            return $this->redirectToRoute('admin_accounting');
        }
        $accounts = $this->em->getRepository(FinanceAccount::class)->findBy(['club' => $club], ['code' => 'ASC']);
        $balances = [];
        foreach ($accounts as $account) { $balances[$account->getId()] = $this->ledger->balanceUntil($account); }
        return $this->render('admin/accounting/index.html.twig', [
            'accounts' => $accounts,
            'balances' => $balances,
            'transactions' => $this->em->getRepository(FinanceTransaction::class)->findBy(['club' => $club], ['effectiveAt' => 'DESC', 'id' => 'DESC'], 100),
            'closes' => $this->em->getRepository(FinanceAnnualClose::class)->findBy(['club' => $club], ['year' => 'DESC']),
        ]);
    }

    private function csrf(Request $request, string $id): void
    {
        if (!$this->isCsrfTokenValid($id, $request->request->getString('_token'))) { throw $this->createAccessDeniedException(); }
    }

    private function amountCents(string $amount): int
    {
        $amount = str_replace(',', '.', trim($amount));
        if (!preg_match('/^\d{1,8}(?:\.\d{1,2})?$/D', $amount)) { throw $this->createNotFoundException('Ungültiger Betrag.'); }
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');
        $cents = ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
        if ($cents <= 0) { throw $this->createNotFoundException('Betrag muss positiv sein.'); }
        return $cents;
    }
}
