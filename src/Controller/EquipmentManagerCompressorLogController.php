<?php
declare(strict_types=1);
namespace App\Controller;
use App\Entity\{Club,CompressorFillLog,Tank,User};
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request,Response,StreamedResponse};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
#[IsGranted('ROLE_EQUIPMENT_MANAGER')]
final class EquipmentManagerCompressorLogController extends AbstractController
{
    private function club(EntityManagerInterface $em): Club
    {
        $user=$this->getUser();
        $id=$user instanceof User ? $user->getMember()?->getClub()?->getId() : null;
        $club=$id ? $em->find(Club::class,$id) : null;
        if (!$club) { throw $this->createAccessDeniedException('Kein Verein zugeordnet.'); }
        return $club;
    }
    #[Route('/equipment-manager/compressor-log', name:'equipment_manager_compressor_log', methods:['GET','POST'])]
    public function index(Request $request, EntityManagerInterface $em, \App\Service\EquipmentFillingService $filling): Response
    {
        $club=$this->club($em);
        if ($request->isMethod('POST')) {
            $this->denyAccessUnlessGranted('ROLE_COMPRESSOR_OPERATOR');
            if (!$this->isCsrfTokenValid('compressor_fill_create',(string)$request->request->get('_token',''))) {
                throw $this->createAccessDeniedException('Ungültiges CSRF-Token.');
            }
            $cylinder=trim((string)$request->request->get('cylinder',''));
            $compressor=trim((string)$request->request->get('compressor',''));
            $start=filter_var($request->request->get('start_bar'), FILTER_VALIDATE_INT);
            $end=filter_var($request->request->get('end_bar'), FILTER_VALIDATE_INT);
            $hoursRaw=trim((string)$request->request->get('hours',''));
            $hours=null;
            if ($hoursRaw!=='' && preg_match('/^\d{1,6}([.,]\d)?$/D',$hoursRaw)) {
                $hours=(int)round((float)str_replace(',','.',$hoursRaw)*10);
            }
            $tankId=filter_var($request->request->get('tank_id'),FILTER_VALIDATE_INT);
            $tank=$tankId ? $em->find(Tank::class,$tankId) : null;
            $errors=[];
            if ($cylinder==='' || mb_strlen($cylinder)>100) $errors[]='Flaschenkennzeichnung fehlt oder ist zu lang.';
            if ($compressor==='' || mb_strlen($compressor)>100) $errors[]='Kompressorkennzeichnung fehlt oder ist zu lang.';
            if ($start===false || $end===false || $start<0 || $end<=0 || $end>350 || $end<=$start) $errors[]='Druckwerte sind ungültig (0–350 bar; Enddruck muss höher sein).';
            if ($hoursRaw!=='' && $hours===null) $errors[]='Betriebsstunden sind ungültig.';
            if ($tankId && (!$tank || $tank->getClub()?->getId()!==$club->getId())) $errors[]='Vereinsflasche nicht gefunden.';
            $filter=trim((string)$request->request->get('filter',''));
            $notes=trim((string)$request->request->get('notes',''));
            if (mb_strlen($filter)>120 || mb_strlen($notes)>2000) $errors[]='Filterreferenz oder Bemerkung zu lang.';
            foreach (['cylinder_check','air_quality_check','filter_check'] as $flag) {
                if ($request->request->get($flag)!=='1') $errors[]='Alle drei Sicherheitsbestätigungen sind erforderlich.';
            }
            if ($errors) {
                foreach (array_unique($errors) as $error) $this->addFlash('danger',$error);
            } else {
                $user=$this->getUser();
                if (!$user instanceof User) throw $this->createAccessDeniedException();
                $log=new CompressorFillLog($club,$user,$cylinder,$compressor,$start,$end);
                $log->setTank($tank);
                $log->setOperatingHoursTenths($hours);
                $log->setFilterReference($filter!==''?$filter:null);
                $log->setNotes($notes!==''?$notes:null);
                $log->setCylinderCheckConfirmed(true);
                $log->setAirQualityConfirmed(true);
                $log->setFilterCheckConfirmed(true);
                try { $filling->save($club, $log, $request->request->getString('fee')); }
                catch (\DomainException $e) {
                    $this->addFlash('danger', $e->getMessage());
                    return $this->redirectToRoute('equipment_manager_compressor_log', [], Response::HTTP_SEE_OTHER);
                }
                $this->addFlash('success','Füllung #'.$log->getId().' dokumentiert.');
                return $this->redirectToRoute('equipment_manager_compressor_log',[],Response::HTTP_SEE_OTHER);
            }
        }
        $logs=$em->getRepository(CompressorFillLog::class)->findBy(['club'=>$club],['filledAt'=>'DESC','id'=>'DESC'],200);
        $tanks=$em->getRepository(Tank::class)->findBy(['club'=>$club],['id'=>'ASC']);
        return $this->render('equipment_manager/compressor_log.html.twig',['logs'=>$logs,'tanks'=>$tanks]);
    }
    #[Route('/equipment-manager/compressor-log/export.csv', name:'equipment_manager_compressor_log_csv', methods:['GET'])]
    public function csv(EntityManagerInterface $em): Response
    {
        $club=$this->club($em);
        $logs=$em->getRepository(CompressorFillLog::class)->findBy(['club'=>$club],['filledAt'=>'ASC','id'=>'ASC']);
        $response=new StreamedResponse(static function() use ($logs): void {
            $out=fopen('php://output','wb');
            fwrite($out,"\xEF\xBB\xBF");
            fputcsv($out,['ID','Datum','Flasche','Kompressor','Gas','Anfangsdruck bar','Enddruck bar','Betriebsstunden','Filter','Flasche geprüft','Luftqualität bestätigt','Filter geprüft','Füller','Bemerkung'],';');
            foreach ($logs as $log) {
                $safe=static function(string $s): string { return preg_match('/^[=+@\-\t\r]/u',$s) ? "'".$s : $s; };
                fputcsv($out,[$log->getId(),$log->getFilledAt()->format('Y-m-d H:i:s'),$safe($log->getCylinderIdentification()),$safe($log->getCompressorIdentification()),$log->getGasType(),$log->getStartPressureBar(),$log->getEndPressureBar(),$log->getOperatingHoursTenths()===null?'':number_format($log->getOperatingHoursTenths()/10,1,',',''),$safe($log->getFilterReference()??''),'ja','ja','ja',$safe((string)$log->getRecordedBy()),$safe($log->getNotes()??'')],';');
            }
            fclose($out);
        });
        $response->headers->set('Content-Type','text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition','attachment; filename="kompressor-fuelllog.csv"');
        return $response;
    }
}
