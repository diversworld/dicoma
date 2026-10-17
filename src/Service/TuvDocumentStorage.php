<?php
declare(strict_types=1);
namespace App\Service;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
final class TuvDocumentStorage {
 public function __construct(#[Autowire('%kernel.project_dir%')] private readonly string $root){}
 private function directory():string{$path=$this->root.'/var/private/tuv_documents';if(!is_dir($path)&&!mkdir($path,0700,true)&&!is_dir($path))throw new \RuntimeException('Privater Ordner nicht verfügbar');return $path;}
 public function path(string $name):string{if(!preg_match('/^[a-f0-9]{40}\.(pdf|jpg|png)$/D',$name))throw new \InvalidArgumentException('Ungültiger Dateiname');return $this->directory().'/'.$name;}
 public function store(UploadedFile $file):array{
  if(!$file->isValid()||$file->getSize()===false||$file->getSize()>5*1024*1024)throw new \InvalidArgumentException('Datei ungültig oder über 5 MB');
  $mime=(new \finfo(FILEINFO_MIME_TYPE))->file($file->getPathname());$allowed=['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png'];
  if(!isset($allowed[$mime]))throw new \InvalidArgumentException('Nur PDF, JPG, PNG erlaubt');
  $sha=hash_file('sha256',$file->getPathname());$name=bin2hex(random_bytes(20)).'.'.$allowed[$mime];$original=$file->getClientOriginalName();$size=(int)$file->getSize();$file->move($this->directory(),$name);
  return [$name,$original,$mime,$size,$sha];
 }
}
