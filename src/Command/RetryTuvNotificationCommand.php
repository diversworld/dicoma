<?php
declare(strict_types=1);
namespace App\Command;
use App\Entity\TuvNotification;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name:'app:tuv:retry-notification',description:'Gibt eine fehlgeschlagene TÜV-Mail nach Prüfung erneut frei')]
final class RetryTuvNotificationCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $em){parent::__construct();}
    protected function configure():void{$this->addArgument('id',InputArgument::REQUIRED,'Nachrichten-ID');}
    protected function execute(InputInterface $input,OutputInterface $output):int
    {
        $id=filter_var($input->getArgument('id'),FILTER_VALIDATE_INT);
        if (!$id || $id<1) return Command::INVALID;
        $item=$this->em->getRepository(TuvNotification::class)->find($id);
        if (!$item || $item->getState()!=='failed') {
            $output->writeln('<error>Nachricht nicht vorhanden oder nicht fehlgeschlagen.</error>');
            return Command::FAILURE;
        }
        $item->resetForRetry();
        $this->em->flush();
        $output->writeln('Nachricht #'.$id.' erneut vorgemerkt. Der Versand erfolgt mit dem nächsten Versandlauf.');
        return Command::SUCCESS;
    }
}
