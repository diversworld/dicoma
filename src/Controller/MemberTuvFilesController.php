<?php
declare(strict_types=1);
namespace App\Controller;
use App\Entity\{User,TuvDocument,TuvInvoice};
use App\Service\TuvDocumentStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
#[IsGranted('ROLE_MEMBER')]
final class MemberTuvFilesController extends AbstractController {
 private function member(): \App\Entity\Member {
  $user=$this->getUser();
  if (!$user instanceof User || !$user->getMember()) throw $this->createAccessDeniedException();
  return $user->getMember();
 }
 #[Route('/my/equipment/tuv/document/{id}',name:'member_tuv_document_download',requirements:['id'=>'\d+'],methods:['GET'])]
 public function document(int $id,EntityManagerInterface $em,TuvDocumentStorage $storage):Response {
  $doc=$em->getRepository(TuvDocument::class)->find($id);
  if (!$doc || $doc->getBooking()->getMember()?->getId()!==$this->member()->getId()) throw $this->createNotFoundException();
  $path=$storage->path($doc->getStoredName());
  if (!is_file($path)) throw $this->createNotFoundException('Datei nicht gefunden');
  $response=$this->file($path,$doc->getOriginalName());
  $response->headers->set('Cache-Control','private, no-store');
  $response->headers->set('X-Content-Type-Options','nosniff');
  return $response;
 }
 #[Route('/my/equipment/tuv/invoice/{id}',name:'member_tuv_invoice',requirements:['id'=>'\d+'],methods:['GET'])]
 public function invoice(int $id,EntityManagerInterface $em):Response {
  $invoice=$em->getRepository(TuvInvoice::class)->find($id);
  if (!$invoice || $invoice->getBooking()->getMember()?->getId()!==$this->member()->getId()) throw $this->createNotFoundException();
  $response=$this->render('member_equipment/tuv_invoice_print.html.twig',['invoice'=>$invoice]);
  $response->headers->set('Cache-Control','private, no-store');
  return $response;
 }
}
