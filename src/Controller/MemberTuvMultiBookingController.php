<?php
declare(strict_types=1);
namespace App\Controller;

use App\Entity\{User, MemberTank, TankCheck, MemberTankCheckBooking, MemberTankCheckBookingItem, TuvBookingGroup, TuvGroupBooking, TuvInvoiceSettings};
use App\Service\{TuvArticleSelector, TuvPriceCalculator};
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_MEMBER')]
final class MemberTuvMultiBookingController extends AbstractController
{
    #[Route('/my/equipment/checks/{id}/multiple', name: 'member_tuv_multiple', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    public function index(int $id, Request $request, EntityManagerInterface $em, TuvArticleSelector $selector): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User || !$user->getMember()) {
            throw $this->createAccessDeniedException('Kein Mitgliedsprofil.');
        }
        $member = $user->getMember();
        $settings = $member->getClub() ? $em->getRepository(TuvInvoiceSettings::class)->find($member->getClub()->getId()) : null;
        $agencyEnabled = $settings !== null && $settings->isMemberAgencyEnabled();
        $check = $em->find(TankCheck::class, $id);
        if (!$check instanceof TankCheck || !$check->isPublished() || !$check->getCheckDate() || $check->getCheckDate()->format('Y-m-d') < date('Y-m-d')) {
            throw $this->createNotFoundException('TÜV-Termin nicht verfügbar.');
        }
        if ($check->getRegistrationDeadline() && $check->getRegistrationDeadline()->format('Y-m-d') < date('Y-m-d')) {
            throw $this->createNotFoundException('Anmeldefrist abgelaufen.');
        }
        $tanks = $em->getRepository(MemberTank::class)->findBy(['member' => $member], ['label' => 'ASC']);
        $rows = [];
        foreach ($tanks as $tank) {
            if ($em->getRepository(MemberTankCheckBooking::class)->findOneBy(['memberTank' => $tank, 'tankCheck' => $check])) continue;
            try {
                $articles = [];
                foreach ($selector->eligible($check, $tank) as $article) {
                    $price = TuvPriceCalculator::fromArticle($article);
                    $articles[] = ['article' => $article, 'price' => $price, 'required' => $selector->effectiveMode($article) === 'required'];
                }
                if ($articles !== []) $rows[] = ['tank' => $tank, 'articles' => $articles];
            } catch (\DomainException) {
                // Incorrect tariff configuration must not make other tanks unbookable.
            }
        }
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('member_tuv_multiple_'.$id, (string) $request->request->get('_token', ''))) {
                throw $this->createAccessDeniedException('Ungültiges CSRF-Token.');
            }
            try {
                if ($agencyEnabled && $request->request->get('agency_consent') !== '1') {
                    throw new \DomainException('Bitte die Beauftragung im eigenen Namen bestaetigen.');
                }
                $postedTanks = $request->request->all('tanks');
                if (!is_array($postedTanks) || $postedTanks === [] || count($postedTanks) > 30) throw new \DomainException('Bitte 1 bis 30 eigene Flaschen auswählen.');
                $ids = [];
                foreach ($postedTanks as $value) {
                    if (!is_string($value) || !ctype_digit($value) || (int)$value < 1 || isset($ids[(int)$value])) throw new \DomainException('Ungültige oder doppelte Flaschenauswahl.');
                    $ids[(int)$value] = true;
                }
                $postedArticles = $request->request->all('articles');
                $allowed = [];
                foreach ($rows as $row) $allowed[$row['tank']->getId()] = $row['tank'];
                if (array_diff_key($ids, $allowed)) throw new \DomainException('Mindestens eine Flasche ist nicht mehr verfügbar.');
                $em->wrapInTransaction(function (EntityManagerInterface $em) use ($ids, $postedArticles, $allowed, $check, $member, $selector, $user, $agencyEnabled): void {
                    // Re-check all ownership and uniqueness within the transaction.
                    $group = new TuvBookingGroup($check, (string) $user->getUserIdentifier());
                    if ($agencyEnabled) { $group->confirmAgency('member-agency-v1'); }
                    $em->persist($group);
                    foreach (array_keys($ids) as $tankId) {
                        $tank = $em->getRepository(MemberTank::class)->findOneBy(['id' => $tankId, 'member' => $member]);
                        if (!$tank || $em->getRepository(MemberTankCheckBooking::class)->findOneBy(['memberTank' => $tank, 'tankCheck' => $check])) {
                            throw new \DomainException('Eine Flasche wurde inzwischen angemeldet oder gehört nicht zu deinem Profil.');
                        }
                        $raw = $postedArticles[(string)$tankId] ?? [];
                        if (!is_array($raw)) throw new \DomainException('Ungültige Leistungsauswahl.');
                        $chosen = $selector->selected($selector->eligible($check, $tank), $raw);
                        $booking = (new MemberTankCheckBooking())->setMember($member)->setMemberTank($tank)->setTankCheck($check);
                        $em->persist($booking);
                        $em->persist(new TuvGroupBooking($group, $booking));
                        foreach ($chosen as $article) {
                            $price = TuvPriceCalculator::fromArticle($article);
                            $em->persist((new MemberTankCheckBookingItem())->setBooking($booking)->setArticle($article)
                                ->setTitleSnapshot((string)$article->getTitle())->setNetSnapshot($price['net'])->setGrossSnapshot($price['gross']));
                        }
                    }
                });
                $this->addFlash('success', count($ids).' Flasche(n) gemeinsam zum TÜV angemeldet.');
                return $this->redirectToRoute('member_equipment_index', [], Response::HTTP_SEE_OTHER);
            } catch (\DomainException $e) {
                $this->addFlash('danger', $e->getMessage());
            } catch (UniqueConstraintViolationException) {
                $this->addFlash('danger', 'Mindestens eine Flasche wurde inzwischen angemeldet. Bitte die Auswahl aktualisieren.');
            }
            return $this->redirectToRoute('member_tuv_multiple', ['id' => $id], Response::HTTP_SEE_OTHER);
        }
        return $this->render('member_equipment/multiple_booking.html.twig', ['check' => $check, 'rows' => $rows, 'agencyEnabled' => $agencyEnabled]);
    }
}
