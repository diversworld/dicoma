<?php
declare(strict_types=1);
namespace App\Controller;
use App\Entity\{Club,User,Tank,CompressorFillSession,CompressorFillBatch,CompressorFillCylinder,CompressorMaintenance};
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request,Response,StreamedResponse};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
#[IsGranted('ROLE_EQUIPMENT_MANAGER')]
final class EquipmentManagerCompressorEveningController extends AbstractController {
 private function club(EntityManagerInterface $em):Club {
  $u=$this->getUser();$id=$u instanceof User?$u->getMember()?->getClub()?->getId():null;
  $club=$id?$em->find(Club::class,$id):null;
  if (!$club) throw $this->createAccessDeniedException('Kein Verein zugeordnet.');
  return $club;
 }
 private function user():User {$u=$this->getUser();if (!$u instanceof User)throw $this->createAccessDeniedException();return $u;}
 private function hours(string $raw):?int {if (!preg_match('/^\d{1,6}([.,]\d)?$/D',$raw))return null;return (int)round((float)str_replace(',','.',$raw)*10);}
 #[Route('/equipment-manager/compressor-evenings',name:'equipment_manager_compressor_evenings',methods:['GET','POST'])]
 public function index(Request $request,EntityManagerInterface $em,\App\Service\EquipmentFillingService $filling):Response {
  $club=$this->club($em);$action=(string)$request->request->get('action','');
  if ($request->isMethod('POST')) {
   $this->denyAccessUnlessGranted('ROLE_COMPRESSOR_OPERATOR');
   if (!$this->isCsrfTokenValid('compressor_evening_'.$action,(string)$request->request->get('_token','')))throw $this->createAccessDeniedException('CSRF');
   if ($action==='open') {
    $name=trim((string)$request->request->get('compressor',''));
    $hours=$this->hours(trim((string)$request->request->get('hours','')));
    $notes=trim((string)$request->request->get('notes',''));
    $lastFilter=$em->getRepository(CompressorMaintenance::class)->findOneBy(['club'=>$club,'compressor'=>$name,'kind'=>'filter'],['performedAt'=>'DESC']);
    $today=new \DateTimeImmutable('today');
    if ($name===''||mb_strlen($name)>100||$hours===null||mb_strlen($notes)>2000||$request->request->get('system_check')!=='1'||$request->request->get('filter_check')!=='1'||$request->request->get('air_check')!=='1') {
     $this->addFlash('danger','Anlagenkennung, Betriebsstunden und alle drei tatsaechlich durchgefuehrten Kontrollen sind erforderlich.');
    } elseif (!$lastFilter || !$lastFilter->getDueAt() || $lastFilter->getDueAt()<$today) {
     $this->addFlash('danger','Keine gueltige Filterwartung dokumentiert. Zuerst Filterwechsel und Faelligkeit im Wartungsbuch eintragen.');
    } else {
     $s=new CompressorFillSession($club,$this->user(),$name,$hours,$notes?:null);$em->persist($s);$em->flush();
     $this->addFlash('success','Fuellabend gestartet.');return $this->redirectToRoute('equipment_manager_compressor_evenings',['session'=>$s->getId()],Response::HTTP_SEE_OTHER);
    }
   } elseif ($action==='batch'||$action==='close') {
    $id=filter_var($request->request->get('session_id'),FILTER_VALIDATE_INT);
    $session=$id?$em->find(CompressorFillSession::class,$id):null;
    if (!$session||$session->getClub()->getId()!==$club->getId()||$session->getClosedAt())throw $this->createNotFoundException('Offener Fuellabend nicht gefunden.');
    if ($action==='close') {
     $hours=$this->hours(trim($request->request->getString('closing_hours')));
     try {
      $em->wrapInTransaction(function () use ($em,$session,$club,$hours):void {
       $em->refresh($session,\Doctrine\DBAL\LockMode::PESSIMISTIC_WRITE);
       if ($session->getClub()->getId()!==$club->getId()||$session->getClosedAt())throw new \DomainException('Fuellabend ist bereits geschlossen oder gehoert nicht zum Verein.');
       if ($hours===null||$hours<$session->getOpeningHoursTenths())throw new \DomainException('Abschliessende Betriebsstunden fehlen oder liegen vor dem Anfangsstand.');
       $session->close($hours);
      });
      $this->addFlash('success','Fuellabend abgeschlossen.');
     } catch (\DomainException $e) {$this->addFlash('danger',$e->getMessage());}
     return $this->redirectToRoute('equipment_manager_compressor_evenings',['session'=>$session->getId()],Response::HTTP_SEE_OTHER);
    }
    $pressure=filter_var($request->request->get('pressure'),FILTER_VALIDATE_INT);
    $notes=trim((string)$request->request->get('notes',''));
    $cylinders=[];$seen=[];$error=null;
    for($i=1;$i<=4;$i++) {
     $name=trim((string)$request->request->get('cylinder_'.$i,''));
     $tankId=filter_var($request->request->get('tank_'.$i),FILTER_VALIDATE_INT);
     $volumeRaw=trim((string)$request->request->get('volume_'.$i,''));
     if ($name===''&&!$tankId&&$volumeRaw==='')continue;
     $tank=$tankId?$em->find(Tank::class,$tankId):null;
     if ($tankId&&(!$tank||$tank->getClub()?->getId()!==$club->getId())){$error='Vereinsflasche nicht gefunden.';break;}
     if ($name==='')$name=(string)($tank->getInventory()?:$tank->getSerialnumber());
     if ($volumeRaw==='' && $tank) $volumeRaw=(string)$tank->getSize();
     $volumeRaw=str_replace(',','.',$volumeRaw);
     if (!preg_match('/^\\d{1,3}(\\.\\d)?$/D',$volumeRaw) || (float)$volumeRaw<=0 || (float)$volumeRaw>100) {
      $error='Flaschenvolumen je Flasche erforderlich (0,1 bis 100 Liter).';break;
     }
     $volumeLiters=number_format((float)$volumeRaw,1,'.','');
     if ($name===''||mb_strlen($name)>100||isset($seen[mb_strtolower($name)])){$error='Flaschenkennungen fehlen, sind doppelt oder zu lang.';break;}
     if ($tank && (!$tank->getNextCheckDate() || $tank->getNextCheckDate()->format('Y-m-d') < date('Y-m-d') || $tank->getConditionStatus() !== 'ready')){$error='Eine Vereinsflasche ist nicht fuellbereit.';break;}
     $seen[mb_strtolower($name)]=true;$cylinders[]=[$tank,$name,$volumeLiters];
    }
    if ($pressure===false||$pressure<1||$pressure>350||!$cylinders||mb_strlen($notes)>2000||$request->request->get('cylinder_check')!=='1')$error=$error?:'Druck, Flaschen oder Flaschenpruefung ungueltig.';
    if ($error)$this->addFlash('danger',$error);
    else {
     $batch=new CompressorFillBatch($session,$this->user(),$pressure,$notes?:null);
     $entries=[];foreach($cylinders as $c)$entries[]=new CompressorFillCylinder($batch,$c[0],$c[1],$c[2]);
     try {$filling->save($club,$batch,$request->request->getString('fee'),$entries);}
     catch (\DomainException $e) {$this->addFlash('danger',$e->getMessage());return $this->redirectToRoute('equipment_manager_compressor_evenings',['session'=>$session->getId()],Response::HTTP_SEE_OTHER);}
     $this->addFlash('success',count($cylinders).' Flaschen mit gemeinsamem Enddruck von '.$pressure.' bar dokumentiert.');
     return $this->redirect($this->generateUrl('equipment_manager_compressor_evenings',['session'=>$session->getId()]).'#naechste-fuellung',Response::HTTP_SEE_OTHER);
    }
   } elseif ($action==='maintenance') {
    $name=trim((string)$request->request->get('compressor',''));
    $kind=(string)$request->request->get('kind','');$desc=trim((string)$request->request->get('description',''));
    $date=\DateTimeImmutable::createFromFormat('!Y-m-d',(string)$request->request->get('performed',''));
    $dueRaw=(string)$request->request->get('due','');$due=$dueRaw!==''?\DateTimeImmutable::createFromFormat('!Y-m-d',$dueRaw):null;
    if ($name===''||mb_strlen($name)>100||!in_array($kind,['filter','air_analysis','inspection','repair'],true)||$desc===''||mb_strlen($desc)>2000||!$date||($dueRaw!==''&&!$due)||($due&&$due<$date))$this->addFlash('danger','Wartungsangaben ungueltig.');
    else {$em->persist(new CompressorMaintenance($club,$this->user(),$name,$kind,$desc,$date,$due?:null));$em->flush();$this->addFlash('success','Wartungsnachweis erfasst.');return $this->redirectToRoute('equipment_manager_compressor_evenings',[],Response::HTTP_SEE_OTHER);}
   }
  }
  $selectedId=filter_var($request->query->get('session'),FILTER_VALIDATE_INT);
  $selected=$selectedId?$em->find(CompressorFillSession::class,$selectedId):null;
  if($selected&&$selected->getClub()->getId()!==$club->getId())throw $this->createNotFoundException();
  $sessions=$em->getRepository(CompressorFillSession::class)->findBy(['club'=>$club],['openedAt'=>'DESC'],100);
  $batches=$selected?$em->getRepository(CompressorFillBatch::class)->findBy(['session'=>$selected],['filledAt'=>'DESC']):[];
  $items=[];foreach($batches as $batch)$items[$batch->getId()]=$em->getRepository(CompressorFillCylinder::class)->findBy(['batch'=>$batch]);
  return $this->render('equipment_manager/compressor_evenings.html.twig',['sessions'=>$sessions,'selected'=>$selected,'batches'=>$batches,'items'=>$items,'tanks'=>$em->getRepository(Tank::class)->findBy(['club'=>$club],['id'=>'ASC']),'maintenance'=>$em->getRepository(CompressorMaintenance::class)->findBy(['club'=>$club],['performedAt'=>'DESC'],30)]);
 }
 #[Route('/equipment-manager/compressor-evenings/export.csv',name:'equipment_manager_compressor_evenings_csv',methods:['GET'])]
 public function csv(EntityManagerInterface $em):Response {
  $club=$this->club($em);$sessions=$em->getRepository(CompressorFillSession::class)->findBy(['club'=>$club],['openedAt'=>'ASC']);
  $rows=[];foreach($sessions as $s)foreach($em->getRepository(CompressorFillBatch::class)->findBy(['session'=>$s],['filledAt'=>'ASC']) as $b)foreach($em->getRepository(CompressorFillCylinder::class)->findBy(['batch'=>$b]) as $c)$rows[]=[$s,$b,$c];
  $response=new StreamedResponse(static function()use($rows):void {$out=fopen('php://output','wb');fwrite($out,"\xEF\xBB\xBF");fputcsv($out,['Fuellabend','Datum','Kompressor','Bediener','Betriebsstunden','Fuellvorgang','Fueller','Flasche','Enddruck bar'],';');foreach($rows as $row){[$s,$b,$c]=$row;$safe=static function($v):string {$v=(string)$v;return preg_match('/^[=+@\-\t\r]/u',$v)?"'".$v:$v;};fputcsv($out,[$s->getId(),$s->getOpenedAt()->format('Y-m-d H:i'),$safe($s->getCompressor()),$safe($s->getOperator()),$s->getOpeningHoursTenths()/10,$b->getId(),$safe($b->getRecordedBy()),$safe($c->getIdentification()),$b->getEndPressureBar()],';');}fclose($out);});
  $response->headers->set('Content-Type','text/csv; charset=UTF-8');$response->headers->set('Content-Disposition','attachment; filename="kompressor-viererfuellungen.csv"');return $response;
 }
}
