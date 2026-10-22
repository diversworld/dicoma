<?php
declare(strict_types=1);
namespace App\Controller\Admin;
use App\Entity\{MemberTankCheckBooking,MemberTankCheckBookingItem,TuvInvoice,TuvInvoiceSettings};
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request,Response};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
#[IsGranted('ROLE_SUPER_ADMIN')]
final class TuvInvoiceController extends AbstractController {
 #[Route('/admin/tuv/invoice/issue/{id}',name:'admin_tuv_invoice_issue',requirements:['id'=>'\d+'],methods:['POST'])]
 public function issue(int $id,Request $request,EntityManagerInterface $em):Response {
  if (!$this->isCsrfTokenValid('tuv_invoice_issue_'.$id,(string)$request->request->get('_token',''))) throw $this->createAccessDeniedException();
  $booking=$em->getRepository(MemberTankCheckBooking::class)->find($id);
  if (!$booking || !$booking->getMember() || !$booking->getMemberTank()) throw $this->createNotFoundException();
  $clubForSettings = $booking->getMember()->getClub();
  $invoiceSettings = $clubForSettings ? $em->getRepository(TuvInvoiceSettings::class)->find($clubForSettings->getId()) : null;
  if ($invoiceSettings && $invoiceSettings->isMemberAgencyEnabled()) {
   $this->addFlash('danger', 'TÜV-Einzelaufträge: Keine Vereinsrechnung erstellen. Bitte die gesonderte Auslagenaufstellung verwenden, sobald diese eingerichtet ist.');
   return $this->redirectToRoute('admin_tuv_operations', ['id' => $id], 303);
  }
  if ($booking->getStatus()==='cancelled') {$this->addFlash('danger','Stornierte Anmeldung kann nicht berechnet werden.');return $this->redirectToRoute('admin_tuv_operations',['id'=>$id]);}
  $connection=$em->getConnection();$connection->beginTransaction();
  try {
   // Lock the booking to prevent two simultaneous invoice issues.
   $em->lock($booking,\Doctrine\DBAL\LockMode::PESSIMISTIC_WRITE);
   if ($em->getRepository(TuvInvoice::class)->findOneBy(['booking'=>$booking])) throw new \DomainException('Für diese Anmeldung existiert bereits eine Rechnung.');
   $items=$em->getRepository(MemberTankCheckBookingItem::class)->findBy(['booking'=>$booking]);
   if (!$items) throw new \DomainException('Keine abrechenbaren Leistungen vorhanden.');
   $lines=[];$net=0;$gross=0;
   foreach ($items as $item) {
    $qty=$item->getQuantity();$n=(int)round((float)$item->getNetSnapshot()*100)*$qty;$g=(int)round((float)$item->getGrossSnapshot()*100)*$qty;
    $lines[]=['title'=>$item->getTitleSnapshot(),'quantity'=>$qty,'netCents'=>$n,'grossCents'=>$g];$net+=$n;$gross+=$g;
   }
   $member=$booking->getMember();
   $club=$member->getClub();
   // Rechnungsanschriften werden zum Ausstellungszeitpunkt unveränderlich gespeichert.
   // Keine Platzhalter-Rechnungen mit bloßer Mitgliedsnummer ausstellen.
   if (!$club || !trim((string)$club->getName()) || !trim((string)$club->getStreet()) || !trim((string)$club->getPostalCode()) || !trim((string)$club->getCity())) {
    throw new \DomainException('Rechnung nicht erstellt: Die vollständige Vereinsanschrift fehlt.');
   }
   if (!trim((string)$member->getFullName()) || !trim((string)$member->getStreet()) || !trim((string)$member->getPostalCode()) || !trim((string)$member->getCity())) {
    throw new \DomainException('Rechnung nicht erstellt: Die vollständige Mitgliederanschrift fehlt.');
   }
   $recipient=implode("\n",[$member->getFullName(),$member->getStreet(),trim($member->getPostalCode().' '.$member->getCity())]);
   $issuer=implode("\n",[$club->getName(),$club->getStreet(),trim($club->getPostalCode().' '.$club->getCity())]);
   if (mb_strlen($recipient)>180 || mb_strlen($issuer)>180) {
    throw new \DomainException('Rechnung nicht erstellt: Eine Rechnungsanschrift ist zu lang (maximal 180 Zeichen).');
   }
   $invoice=new TuvInvoice($booking,$recipient,$issuer,$lines,$net,$gross);
   $em->persist($invoice);$em->flush();$invoice->assignNumber();$em->flush();$connection->commit();
   $this->addFlash('success','Rechnungsdatensatz erstellt: '.$invoice->getNumber());
  } catch (\DomainException $e) {
   $connection->rollBack();$this->addFlash('danger',$e->getMessage());
  } catch (\Throwable $e) {
   $connection->rollBack();throw $e;
  }
  return $this->redirectToRoute('admin_tuv_operations',['id'=>$id],303);
 }
}
