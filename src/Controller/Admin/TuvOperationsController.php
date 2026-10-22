<?php
declare(strict_types=1);
namespace App\Controller\Admin;

use App\Entity\{MemberTankCheckBooking, TuvHandover, TuvDocument, TuvBookingGroup, TuvGroupBooking, TuvNotification};
use App\Service\TuvDocumentStorage;
use App\Service\TuvMessageRenderer;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response, BinaryFileResponse};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_SUPER_ADMIN')]
final class TuvOperationsController extends AbstractController {
 private function booking(int $id,EntityManagerInterface $em):MemberTankCheckBooking {
  $booking=$em->getRepository(MemberTankCheckBooking::class)->find($id);
  if(!$booking)throw $this->createNotFoundException('Anmeldung nicht gefunden');
  return $booking;
 }
 private function back(MemberTankCheckBooking $booking,AdminUrlGenerator $urls):string {
  return $urls->unsetAll()->setController(MemberTankCheckBookingCrudController::class)->setAction('operations')->setEntityId($booking->getId())->generateUrl();
 }
 #[Route('/admin/tuv/operations/{id}',name:'admin_tuv_operations',methods:['GET'])]
 public function show(int $id,EntityManagerInterface $em,AdminUrlGenerator $urls):Response {
  $booking=$this->booking($id,$em);
  return $this->redirect($this->back($booking,$urls));
 }
 #[Route('/admin/tuv/operations/{id}/handover/print',name:'admin_tuv_handover_print',methods:['GET'])]
 public function printHandover(int $id,EntityManagerInterface $em):Response {
  $booking=$this->booking($id,$em);
  $handovers=$em->getRepository(TuvHandover::class)->findBy(['booking'=>$booking],['occurredAt'=>'ASC','id'=>'ASC']);
  $response=$this->render('admin/tuv/handover_print.html.twig',[
   'booking'=>$booking,
   'handovers'=>$handovers,
   'generatedAt'=>new \DateTimeImmutable(),
  ]);
  $response->headers->set('Cache-Control','private, no-store');
  $response->headers->set('X-Content-Type-Options','nosniff');
  return $response;
 }
 #[Route('/admin/tuv/operations/{id}/handover',name:'admin_tuv_handover_save',methods:['POST'])]
 public function handover(int $id,Request $request,EntityManagerInterface $em,AdminUrlGenerator $urls):Response {
  $booking=$this->booking($id,$em);$this->csrf($request,$id);
  $type=(string)$request->request->get('type','');$notes=trim((string)$request->request->get('notes',''));
  $allowed=['received'=>['requested','received'],'sent'=>['received','sent'],'returned'=>['sent','returned'],'collected'=>['returned','completed']];
  if(!isset($allowed[$type])||!in_array($booking->getStatus(),$allowed[$type],true)||mb_strlen($notes)>2000){$this->addFlash('danger','Übergabeart, Status oder Bemerkung ungültig.');return $this->redirectToRoute('admin_tuv_operations',['id'=>$id]);}
  $em->persist(new TuvHandover($booking,$type,$this->getUser()?->getUserIdentifier()??'admin',$notes?:null));$em->flush();
  $this->addFlash('success','Übergabe dokumentiert (Buchungsstatus bleibt unverändert).');return $this->redirectToRoute('admin_tuv_operations',['id'=>$id]);
 }
 #[Route('/admin/tuv/operations/{id}/document',name:'admin_tuv_document_upload',methods:['POST'])]
 public function upload(int $id,Request $request,EntityManagerInterface $em,TuvDocumentStorage $storage):Response {
  $booking=$this->booking($id,$em);$this->csrf($request,$id);
  $file=$request->files->get('document');
  if(!$file instanceof \Symfony\Component\HttpFoundation\File\UploadedFile){$this->addFlash('danger','Datei fehlt.');return $this->redirectToRoute('admin_tuv_operations',['id'=>$id]);}
  try{[$name,$original,$mime,$size,$sha]=$storage->store($file);}catch(\InvalidArgumentException $e){$this->addFlash('danger',$e->getMessage());return $this->redirectToRoute('admin_tuv_operations',['id'=>$id]);}
  $em->persist(new TuvDocument($booking,$name,$original,$mime,$size,$sha,$this->getUser()?->getUserIdentifier()??'admin'));$em->flush();
  $this->addFlash('success','Dokument privat und unveränderlich abgelegt.');return $this->redirectToRoute('admin_tuv_operations',['id'=>$id]);
 }
 #[Route('/admin/tuv/document/{id}',name:'admin_tuv_document_download',methods:['GET'])]
 public function download(int $id,EntityManagerInterface $em,TuvDocumentStorage $storage):Response {
  $doc=$em->getRepository(TuvDocument::class)->find($id);if(!$doc)throw $this->createNotFoundException();
  $path=$storage->path($doc->getStoredName());if(!is_file($path))throw $this->createNotFoundException('Datei fehlt');
  return $this->file($path,$doc->getOriginalName());
 }
 #[Route('/admin/tuv/operations/{id}/group',name:'admin_tuv_group_save',methods:['POST'])]
 public function group(int $id,Request $request,EntityManagerInterface $em):Response {
  $booking=$this->booking($id,$em);$this->csrf($request,$id);
  $ids=array_filter(array_unique(array_map('intval',preg_split('/[\s,;]+/',(string)$request->request->get('booking_ids',''))?:[])),fn(int $v)=>$v>0);
  $ids=array_values(array_unique(array_merge([$id],$ids)));
  if(count($ids)<2||count($ids)>30){$this->addFlash('danger','Bitte 2 bis 30 Anmeldungen angeben.');return $this->redirectToRoute('admin_tuv_operations',['id'=>$id]);}
  $bookings=$em->getRepository(MemberTankCheckBooking::class)->findBy(['id'=>$ids]);
  if(count($bookings)!==count($ids)){$this->addFlash('danger','Mindestens eine Anmeldung existiert nicht.');return $this->redirectToRoute('admin_tuv_operations',['id'=>$id]);}
  foreach($bookings as $item){if($item->getTankCheck()?->getId()!==$booking->getTankCheck()?->getId()||$em->getRepository(TuvGroupBooking::class)->findOneBy(['booking'=>$item])){$this->addFlash('danger','Nur ungruppierte Anmeldungen desselben TÜV-Termins sind zulässig.');return $this->redirectToRoute('admin_tuv_operations',['id'=>$id]);}}
  $group=new TuvBookingGroup($booking->getTankCheck(),$this->getUser()?->getUserIdentifier()??'admin');$em->persist($group);
  foreach($bookings as $item)$em->persist(new TuvGroupBooking($group,$item));
  $em->flush();$this->addFlash('success','Sammelbuchung angelegt; einzelne Prüfergebnisse und Preis-Snapshots bleiben erhalten.');return $this->redirectToRoute('admin_tuv_operations',['id'=>$id]);
 }
 #[Route('/admin/tuv/operations/{id}/notify',name:'admin_tuv_notification_queue',methods:['POST'])]
 public function queue(int $id,Request $request,EntityManagerInterface $em,TuvMessageRenderer $renderer, \App\Service\TuvNotificationSender $sender):Response {
  $booking=$this->booking($id,$em);$this->csrf($request,$id);
  $recipient=trim((string)$request->request->get('recipient',''));$kind=(string)$request->request->get('kind','');
  $labels=TuvMessageRenderer::LABELS;
  if(!filter_var($recipient,FILTER_VALIDATE_EMAIL)||!isset($labels[$kind])){$this->addFlash('danger','Empfänger oder Nachrichtentyp ungültig.');return $this->redirectToRoute('admin_tuv_operations',['id'=>$id]);}
  $key=hash('sha256',implode('|',[$booking->getId(),$kind,$booking->getStatus(),strtolower($recipient)]));
  if(!$em->getRepository(TuvNotification::class)->findOneBy(['dedupeKey'=>$key])){
   ['subject'=>$subject,'body'=>$body]=$renderer->render($kind,$booking);
   $notice=new TuvNotification($booking,$key,$recipient,$subject,$body);$em->persist($notice);$em->flush();
   if($sender->isImmediate()) { $result=$sender->sendOne((int)$notice->getId()); $this->addFlash($result==='sent'?'success':'warning', 'Sofortversand: '.$result); }
  }
  $this->addFlash('info','Nachricht angelegt bzw. bereits vorhanden. Versandstatus im Postausgang prüfen.');return $this->redirectToRoute('admin_tuv_operations',['id'=>$id]);
 }
 private function csrf(Request $request,int $id):void {if(!$this->isCsrfTokenValid('tuv_ops_'.$id,(string)$request->request->get('_token','')))throw $this->createAccessDeniedException('CSRF-Prüfung fehlgeschlagen');}
}
