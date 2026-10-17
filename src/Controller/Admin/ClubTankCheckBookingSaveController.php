<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\{Tank, TankCheck, MemberTankCheckBooking, MemberTankCheckBookingItem};
use App\Service\{TuvArticleSelector, TuvPriceCalculator};
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, RedirectResponse};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class ClubTankCheckBookingSaveController extends AbstractController
{
    #[Route('/admin/tuv/club-bookings/save', name: 'admin_tuv_club_booking_save', methods: ['POST'])]
    public function save(Request $request, EntityManagerInterface $em, TuvArticleSelector $selector, AdminUrlGenerator $urls): RedirectResponse
    {
        $back = $urls->unsetAll()->setController(MemberTankCheckBookingCrudController::class)
            ->setAction(Action::INDEX)->generateUrl();
        $checkId = $request->request->get('check_id');
        $tankId = $request->request->get('tank_id');
        if (!is_string($checkId) || !ctype_digit($checkId) || !is_string($tankId) || !ctype_digit($tankId)) {
            throw $this->createNotFoundException('Ungültige Auswahl.');
        }
        if (!$this->isCsrfTokenValid('club_tuv_book_'.$checkId.'_'.$tankId, (string)$request->request->get('_token', ''))) {
            throw $this->createAccessDeniedException('Ungültiger CSRF-Token.');
        }
        $check = $em->getRepository(TankCheck::class)->find((int)$checkId);
        $tank = $em->getRepository(Tank::class)->find((int)$tankId);
        if (!$check || !$tank || !$check->isPublished() || !$check->getCheckDate()
            || $check->getCheckDate()->format('Y-m-d') < date('Y-m-d')
            || ($check->getRegistrationDeadline() && $check->getRegistrationDeadline()->format('Y-m-d') < date('Y-m-d'))) {
            $this->addFlash('danger', 'TÜV-Termin oder Vereinsflasche nicht verfügbar.');
            return $this->redirect($back);
        }
        try {
            if ($em->getRepository(MemberTankCheckBooking::class)->findOneBy(['clubTank' => $tank, 'tankCheck' => $check])) {
                throw new \DomainException('Diese Vereinsflasche ist bereits für den Termin angemeldet.');
            }
            $chosen = $selector->selected($selector->eligibleForClubTank($check, $tank), $request->request->all('article_ids'));
            $booking = (new MemberTankCheckBooking())->setClubTank($tank)->setTankCheck($check);
            $em->persist($booking);
            $netTotal = $grossTotal = 0;
            foreach ($chosen as $article) {
                $pair = TuvPriceCalculator::fromArticle($article);
                $netTotal += TuvPriceCalculator::cents($pair['net']);
                $grossTotal += TuvPriceCalculator::cents($pair['gross']);
                $em->persist((new MemberTankCheckBookingItem())->setBooking($booking)->setArticle($article)
                    ->setTitleSnapshot((string)$article->getTitle())->setNetSnapshot($pair['net'])->setGrossSnapshot($pair['gross']));
            }
            $em->flush();
            $this->addFlash('success', sprintf('Vereinsflasche angemeldet: %s € netto / %s € brutto.',
                TuvPriceCalculator::format($netTotal), TuvPriceCalculator::format($grossTotal)));
        } catch (\DomainException $e) {
            $this->addFlash('danger', $e->getMessage());
        } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException $e) {
            $this->addFlash('danger', 'Diese Vereinsflasche ist bereits für den Termin angemeldet.');
        }
        return $this->redirect($back);
    }
}
