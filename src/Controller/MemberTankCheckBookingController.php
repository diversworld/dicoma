<?php

declare(strict_types=1);
namespace App\Controller;

use App\Entity\{User, MemberTank, TankCheck, MemberTankCheckBooking, MemberTankCheckBookingItem};
use App\Service\{TuvArticleSelector, TuvPriceCalculator};
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_MEMBER')]
final class MemberTankCheckBookingController extends AbstractController
{
    #[Route('/my/equipment/checks/{id}/tanks/{tankId}/services', name: 'member_tuv_services', requirements: ['id' => '\d+', 'tankId' => '\d+'], methods: ['GET'])]
    public function services(int $id, int $tankId, EntityManagerInterface $em, TuvArticleSelector $selector): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User || !$user->getMember()) throw $this->createAccessDeniedException('Kein Mitgliedsprofil.');
        $tank = $em->getRepository(MemberTank::class)->findOneBy(['id' => $tankId, 'member' => $user->getMember()]);
        $check = $em->getRepository(TankCheck::class)->find($id);
        if (!$tank || !$check || !$check->isPublished() || !$check->getCheckDate() || $check->getCheckDate()->format('Y-m-d') < date('Y-m-d')) throw $this->createNotFoundException('Termin nicht verfügbar.');
        if ($check->getRegistrationDeadline() && $check->getRegistrationDeadline()->format('Y-m-d') < date('Y-m-d')) throw $this->createNotFoundException('Anmeldung geschlossen.');
        $items = [];
        foreach ($selector->eligible($check, $tank) as $article) {
            try {
                $pair = TuvPriceCalculator::fromArticle($article);
                $items[] = ['article' => $article, 'mode' => $selector->effectiveMode($article), 'net' => $pair['net'], 'gross' => $pair['gross']];
            } catch (\DomainException $e) { /* Incomplete article cannot be booked. */ }
        }
        return $this->render('member_equipment/booking_form.html.twig', ['tank' => $tank, 'check' => $check, 'items' => $items, 'volume' => $selector->volume($tank)]);
    }

    #[Route('/my/equipment/checks/{id}/book', name: 'member_tank_check_book', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function book(int $id, Request $request, EntityManagerInterface $em, TuvArticleSelector $selector): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User || !$user->getMember()) throw $this->createAccessDeniedException('Kein Mitgliedsprofil.');
        if (!$this->isCsrfTokenValid('member_check_book_'.$id, (string)$request->request->get('_token', ''))) throw $this->createAccessDeniedException('CSRF-Token ungültig.');
        $raw = $request->request->get('member_tank_id');
        if (!is_string($raw) || !ctype_digit($raw) || (int)$raw < 1) throw $this->createNotFoundException('Flasche fehlt.');
        $tank = $em->getRepository(MemberTank::class)->findOneBy(['id' => (int)$raw, 'member' => $user->getMember()]);
        $check = $em->getRepository(TankCheck::class)->find($id);
        if (!$tank || !$check || !$check->isPublished() || !$check->getCheckDate() || $check->getCheckDate()->format('Y-m-d') < date('Y-m-d')) throw $this->createNotFoundException('Termin nicht verfügbar.');
        if ($check->getRegistrationDeadline() && $check->getRegistrationDeadline()->format('Y-m-d') < date('Y-m-d')) {
            $this->addFlash('danger', 'Anmeldeschluss abgelaufen.');
            return $this->redirectToRoute('member_equipment_index', [], 303);
        }
        $posted = $request->request->all('article_ids');
        try {
            $eligible = $selector->eligible($check, $tank);
            $chosen = $selector->selected($eligible, $posted);
            $lines = [];
            $netTotal = $grossTotal = 0;
            foreach ($chosen as $article) {
                $pair = TuvPriceCalculator::fromArticle($article);
                $netTotal += TuvPriceCalculator::cents($pair['net']);
                $grossTotal += TuvPriceCalculator::cents($pair['gross']);
                $lines[] = [$article, $pair];
            }
            $existing = $em->getRepository(MemberTankCheckBooking::class)->findOneBy(['memberTank' => $tank, 'tankCheck' => $check]);
            if ($existing) throw new \DomainException('Diese Flasche ist bereits angemeldet. Auch stornierte Buchungen müssen separat reaktiviert werden.');
            $booking = (new MemberTankCheckBooking())->setMember($user->getMember())->setMemberTank($tank)->setTankCheck($check);
            $em->persist($booking);
            foreach ($lines as [$article, $pair]) {
                $item = (new MemberTankCheckBookingItem())->setBooking($booking)->setArticle($article)->setTitleSnapshot((string)$article->getTitle())->setNetSnapshot($pair['net'])->setGrossSnapshot($pair['gross']);
                $em->persist($item);
            }
            $em->flush();
            $this->addFlash('success', sprintf('TÜV-Anmeldung gespeichert: %s € netto / %s € brutto.', TuvPriceCalculator::format($netTotal), TuvPriceCalculator::format($grossTotal)));
        } catch (\DomainException $e) {
            $this->addFlash('danger', $e->getMessage());
        } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException $e) {
            $this->addFlash('danger', 'Diese Flasche ist bereits angemeldet.');
        }
        return $this->redirectToRoute('member_equipment_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/my/equipment/bookings/{id}/cancel', name: 'member_tank_check_cancel', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function cancel(int $id, Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User || !$user->getMember()) throw $this->createAccessDeniedException('Kein Mitgliedsprofil.');
        if (!$this->isCsrfTokenValid('member_check_cancel_'.$id, (string)$request->request->get('_token', ''))) throw $this->createAccessDeniedException('CSRF-Token ungültig.');
        $booking = $em->getRepository(MemberTankCheckBooking::class)->findOneBy(['id' => $id, 'member' => $user->getMember()]);
        if (!$booking) throw $this->createNotFoundException('Anmeldung nicht gefunden.');
        if ($booking->getStatus() !== 'requested' || !$booking->getTankCheck()?->getCheckDate() || $booking->getTankCheck()->getCheckDate()->format('Y-m-d') <= date('Y-m-d')) {
            $this->addFlash('danger', 'Stornierung nicht mehr möglich.');
        } else {
            $booking->setStatus('cancelled');
            $em->flush();
            $this->addFlash('success', 'Anmeldung storniert.');
        }
        return $this->redirectToRoute('member_equipment_index', [], Response::HTTP_SEE_OTHER);
    }
}
